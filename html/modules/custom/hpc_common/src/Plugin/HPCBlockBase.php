<?php

namespace Drupal\hpc_common\Plugin;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\hpc_common\Helpers\ContextHelper;
use Drupal\hpc_common\Helpers\RequestHelper;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for HPC Block plugins.
 */
abstract class HPCBlockBase extends BlockBase implements HPCPluginInterface, ContainerFactoryPluginInterface {

  /**
   * ID for the current page.
   *
   * @var string
   */
  protected $page;

  /**
   * ID for the current page variant.
   *
   * @var string
   */
  protected $pageVariant;

  /**
   * Title of the current page.
   *
   * @var string
   */
  protected $pageTitle;

  /**
   * URL for the current page.
   *
   * @var string
   */
  protected $currentUri;

  /**
   * Region where a block is rendered.
   *
   * @var string
   */
  protected $region;

  /**
   * Flags whether field contexts have already been injected.
   *
   * @var bool
   */
  protected $injectedFieldContexts = FALSE;

  /**
   * The key-value store to use for storing non-exportable configuration.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactory
   */
  protected $keyValueFactory;

  /**
   * The key-value store to use for storing non-exportable configuration.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $keyValue;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The router.
   *
   * @var \Drupal\Core\Routing\Router
   */
  protected $router;

  /**
   * The endpoint query to retrieve API data.
   *
   * @var \Drupal\hpc_api\Query\EndpointQuery
   */
  protected $endpointQuery;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);

    // Set our own properties.
    $instance->requestStack = $container->get('request_stack');
    $instance->router = $container->get('router.no_access_checks');
    $instance->keyValueFactory = $container->get('keyvalue');
    $instance->endpointQuery = $container->get('hpc_api.endpoint_query');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->fileSystem = $container->get('file_system');

    // Mostly used to support meta data in downloads.
    $instance->setCurrentUri();

    // Inject context based on node fields.
    $instance->injectFieldContexts();

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextMapping() {
    // Get the context mapping that the block has, based on it's stored
    // configuration.
    $context_mapping = parent::getContextMapping();

    // And match that with the context definitions that the block plugin
    // actually declares. This allows for block plugins to remove previously
    // used context definitions without running into
    // Drupal\Component\Plugin\Exception\ContextException which results in a
    // WSOD and is not fixable via the UI.
    // We do not explicitely check whether new context definitions have been
    // added, which are not yet stored in the blocks storage, as this should
    // "only" result in a broken block display, but not in a fatal error.
    $plugin_definition = $this->getPluginDefinition();
    $context_definitions = $plugin_definition['context_definitions'] ?? [];

    return array_intersect_key($context_mapping, $context_definitions);
  }

  /**
   * {@inheritdoc}
   */
  public function hasContext($key) {
    $contexts = $this->getContexts();
    return array_key_exists($key, $contexts);
  }

  /**
   * Returns a key/value storage collection.
   *
   * @param string $collection
   *   Name of the key/value collection to return.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   *   The key/value store.
   */
  public function keyValue($collection) {
    if (!$this->getUuid()) {
      return NULL;
    }
    if (!$this->keyValue) {
      $this->keyValue = $this->keyValueFactory->get(implode('.', [
        $this->getKeyValueBaseId(),
        $this->getPluginId(),
        $this->getUuid(),
        $collection,
      ]));
    }
    return $this->keyValue;
  }

  /**
   * Get the base id for the key value store of block instances.
   */
  public function getKeyValueBaseId() {
    return 'hpc_block';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    // Make sure we always use up to data data source information.
    $plugin_definition = $this->getPluginDefinition();
    $this->configuration['data_sources'] = $plugin_definition['data_sources'] ?? NULL;
    return $this->configuration;
  }

  /**
   * Inject contexts based on the current nodes field values.
   */
  public function injectFieldContexts() {
    if ($this->injectedFieldContexts) {
      return;
    }

    $plugin_definition = $this->getPluginDefinition();
    $field_context_mapping = !empty($plugin_definition['field_context_mapping']) ? $plugin_definition['field_context_mapping'] : NULL;

    if (!$field_context_mapping) {
      $this->injectedFieldContexts = TRUE;
      return;
    }

    $node = $this->getNodeFromContexts();
    if (!$node) {
      $this->injectedFieldContexts = TRUE;
      return;
    }

    foreach ($field_context_mapping as $context_key => $context_definition) {
      $field_name = is_string($context_definition) ? $context_definition : $context_definition['field_name'];
      $context_type = is_string($context_definition) ? 'integer' : $context_definition['type'];
      if ($node->hasField($field_name)) {
        $value = $node->get($field_name)->first()->getValue()['value'];
        $label = $node->$field_name->getFieldDefinition()->getLabel();

        if (empty($plugin_definition['context_definitions'][$context_key])) {
          // Create a new context.
          $context_definition = new ContextDefinition($context_type, $label, FALSE);
          $context = new Context($context_definition, $value);
          $this->setContext($context_key, $context);
        }
        else {
          // Overwrite the existing context value if there is any.
          $this->setContextValue($context_key, $value);
        }

      }
    }
    $this->injectedFieldContexts = TRUE;
  }

  /**
   * Get the current node context if any.
   */
  public function getNodeFromContexts($contexts = NULL) {
    if ($contexts === NULL) {
      $contexts = $this->getContexts();
    }
    return ContextHelper::getNodeFromContexts($contexts);
  }

  /**
   * Set the URI for current page.
   */
  public function setCurrentUri($current_uri = NULL) {
    if ($current_uri === NULL) {
      $request = $this->requestStack->getCurrentRequest();
      // This might come from an IPE or form state context ($_POST).
      $current_path = $request->request->get('currentPath');
      // Or from a query argument, i.e. in download contexts.
      $uri = $request->query->get('uri') ?? $request->query->get('current_uri');
      if (!empty($current_path)) {
        $current_uri = $current_path;
      }
      elseif (!empty($uri)) {
        $current_uri = $uri;
      }
      else {
        $current_uri = $request->getRequestUri();
      }
    }
    $this->currentUri = '/' . ltrim($current_uri, '/');
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentUri() {
    if ($this->currentUri === NULL) {
      $this->setCurrentUri();
    }
    return $this->currentUri;
  }

  /**
   * Set the region where this plugin is displayed.
   */
  public function setRegion($region) {
    $this->region = $region;
  }

  /**
   * Get the region where this plugin is displayed.
   */
  public function getRegion() {
    if (!empty($this->region)) {
      return $this->region;
    }
    $plugin_configuration = $this->getConfiguration();
    return !empty($plugin_configuration['region']) ? $plugin_configuration['region'] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getUuid() {
    $plugin_configuration = $this->getConfiguration();
    if (!empty($plugin_configuration['uuid'])) {
      return $plugin_configuration['uuid'];
    }
    if ($uuid = RequestHelper::getQueryArgument('block_uuid')) {
      return $uuid;
    }
    if ($this->requestStack) {
      return $this->requestStack->getCurrentRequest()->attributes->get('uuid');
    }
    return NULL;
  }

  /**
   * Get all available page parameters.
   *
   * Especially in ajax contexts this can make a difference to identify the
   * actual page that we are on.
   *
   * @return array
   *   An array of page parameters.
   */
  protected function getAllAvailablePageParameters($page_parameters = NULL) {
    if ($page_parameters === NULL) {
      $page_parameters = [];
    }

    try {
      $page_parameters = $this->router->match($this->getCurrentUri());
    }
    catch (\Exception $e) {
      return $page_parameters;
    }
    $page_parameters = array_filter($page_parameters, function ($key) {
      return $key[0] != '_';
    }, ARRAY_FILTER_USE_KEY);

    return $page_parameters;
  }

  /**
   * Set a page identifier for the current page.
   */
  public function setPage($page_parameters = NULL) {
    $page_parameters = $this->getAllAvailablePageParameters($page_parameters);

    if (!empty($page_parameters['page_manager_page'])) {
      // Page manager page.
      $this->page = $page_parameters['page_manager_page']->id();
    }
    elseif (!empty($page_parameters['panels_storage_id'])) {
      // Used when in configuration editing context with Panels IPE.
      [$this->page] = explode('-', $page_parameters['panels_storage_id'], 2);
    }
    elseif (!empty($page_parameters['tempstore_id']) && $page_parameters['tempstore_id'] == 'page_manager.page') {
      // Used when configuring using the Panels UI.
      [$this->page] = explode('-', $page_parameters['machine_name'], 2);
    }
    elseif (!empty($page_parameters['node'])) {
      // Node view context.
      $entity_storage = $this->entityTypeManager->getStorage('node');
      $node = is_object($page_parameters['node']) ? $page_parameters['node'] : $entity_storage->load($page_parameters['node']);
      $this->page = $node->bundle() . '_node';
    }
    elseif (!empty($page_parameters['section_storage']) && $page_parameters['section_storage'] instanceof OverridesSectionStorage) {
      // Layout builder editing context.
      $entity = $page_parameters['section_storage']->getContextValue('entity');
      if ($entity->bundle() == 'page_variant') {
        // Page variant.
        /** @var \Drupal\page_manager\Entity\PageVariant $entity */
        $this->page = $entity->getPage()->id();
      }
      else {
        // Content entity, e.g. node.
        $this->page = $entity->bundle() . '_' . $entity->getEntityTypeId();
      }
    }
    elseif (!empty($page_parameters['entity'])) {
      // Content entity, e.g. node.
      $entity = $page_parameters['entity'];
      $this->page = $entity->bundle() . '_' . $entity->getEntityTypeId();
    }
    else {
      // No page identified.
      $this->page = FALSE;
    }
  }

  /**
   * Get a page identifier for the current page.
   */
  public function getPage() {
    if ($this->page === NULL) {
      $this->setPage($this->getPageArguments());
    }
    return $this->page;
  }

  /**
   * Set a page identifier for the current page.
   */
  public function setPageVariant($page_parameters = NULL) {
    $page_parameters = $this->getAllAvailablePageParameters($page_parameters);

    if (!empty($page_parameters['page_manager_page_variant'])) {
      $this->pageVariant = $page_parameters['page_manager_page_variant']->id();
    }
    elseif (!empty($page_parameters['panels_storage_id'])) {
      // Used when in configuration editing context with Panels IPE.
      $this->pageVariant = $page_parameters['panels_storage_id'];
    }
    elseif (!empty($page_parameters['tempstore_id']) && $page_parameters['tempstore_id'] == 'page_manager.page') {
      // Used when configuring using the Panels UI.
      $this->pageVariant = explode('-', $page_parameters['machine_name'], 2)[0];
    }
    elseif (!empty($page_parameters['node'])) {
      $this->pageVariant = 'node:' . $page_parameters['node']->bundle();
    }
    else {
      $this->pageVariant = FALSE;
    }
  }

  /**
   * Get a page identifier for the current page.
   */
  public function getPageVariant() {
    if ($this->pageVariant === NULL) {
      $this->setPageVariant($this->getPageArguments());
    }
    return $this->pageVariant;
  }

  /**
   * Set a page identifier for the current page.
   */
  public function setPageTitle($page_parameters = NULL) {
    $page_parameters = $this->getAllAvailablePageParameters($page_parameters);

    if (!empty($page_parameters['node'])) {
      $this->pageTitle = $page_parameters['node']->getTitle();
    }
    elseif (!empty($page_parameters['page_manager_page_variant'])) {
      $variant_plugin = $page_parameters['page_manager_page_variant']->getVariantPlugin();
      try {
        // This build call might happen before contexts are initialized, so
        // better catch exceptions here.
        $build = $variant_plugin->build();
        $this->pageTitle = $build['#title'];
      }
      catch (\Exception $e) {
        $this->pageTitle = NULL;
      }
    }
    elseif (!empty($page_parameters['page_manager_page'])) {
      $this->pageTitle = $page_parameters['page_manager_page']->label();
    }
    else {
      $this->pageTitle = FALSE;
    }
  }

  /**
   * Get a page identifier for the current page.
   */
  public function getPageTitle() {
    if ($this->pageTitle === NULL) {
      $this->setPageTitle($this->getPageArguments());
    }
    return $this->pageTitle;
  }

  /**
   * Get the specified named argument for the current page.
   */
  protected function getPageArgument($key) {
    $context = $this->hasContext($key) ? $this->getContext($key) : NULL;
    return $context && $context->hasContextValue() ? $context->getContextValue() : NULL;
  }

  /**
   * Get all named arguments for the current page.
   */
  public function getPageArguments() {
    $context_values = [];

    foreach ($this->getContexts() as $key => $context) {
      if (!$context->hasContextValue()) {
        continue;
      }
      $context_value = $context->getContextValue();
      if (!$context_value || !is_scalar($context_value)) {
        continue;
      }
      $context_values[$key] = $context_value;
    }
    return $context_values;
  }

  /**
   * Get the node for the current page.
   *
   * @return \Drupal\node\NodeInterface
   *   The page node if found.
   */
  public function getPageNode() {
    $node = $this->getNodeFromContexts();
    if ($node) {
      return $node;
    }

    $page_arguments = $this->getAllAvailablePageParameters();
    if (!empty($page_arguments['section_storage']) && $page_arguments['section_storage'] instanceof SectionStorageInterface) {
      /** @var \Drupal\layout_builder\SectionStorageInterface $section_storage */
      $section_storage = $page_arguments['section_storage'];
      $section_contexts = array_keys($section_storage->getContexts());
      $entity = in_array('entity', $section_contexts) ? $section_storage->getContextValue('entity') : NULL;
      return $entity instanceof NodeInterface ? $entity : NULL;
    }
    if (!empty($page_arguments['node'])) {
      return $page_arguments['node'];
    }
    if (!empty($page_arguments['node_from_original_id'])) {
      return $page_arguments['node_from_original_id'];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $label = parent::label();

    if (empty($this->getPageArguments())) {
      return $label;
    }

    foreach ($this->getPageArguments() as $keyword => $value) {
      if (!$value) {
        continue;
      }
      if (is_string($value) || is_int($value)) {
        $label = str_replace('{' . $keyword . '}', $value, $label);
      }
      elseif ($value instanceof Node) {
        // @todo Why is this empty?
      }
    }
    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviewFallbackString() {
    return $this->t('"@block" block', ['@block' => parent::label()]);
  }

  /**
   * {@inheritdoc}
   */
  abstract public function build();

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function baseConfigurationDefaults() {
    $defaults = parent::baseConfigurationDefaults();
    if (!empty($this->pluginDefinition['data_sources'])) {
      $defaults['data_sources'] = $this->pluginDefinition['data_sources'];
    }
    $defaults['uuid'] = $this->getUuid();
    return $defaults;
  }

  /**
   * Get a query handler for this block.
   *
   * This returns either the requested named handler if it exists, or the only
   * one defined if no source key is given.
   *
   * @param string $source_key
   *   The source key that should be used to retrieve data for a block.
   *
   * @return Drupal\hpc_api\EndpointQuery
   *   The query handler class.
   */
  protected function getQueryHandler($source_key) {
    $configuration = $this->getConfiguration();
    if (empty($configuration['data_sources'])) {
      return NULL;
    }

    $sources = $configuration['data_sources'];
    $definition = !empty($sources[$source_key]) ? $sources[$source_key] : NULL;
    if (!$definition || empty($definition['arguments'])) {
      return NULL;
    }
    $query_handler = $this->endpointQuery;
    $query_handler->setArguments($definition['arguments']);
    return $query_handler;
  }

  /**
   * Get data for this block.
   *
   * This returns either the data retrieved by the requested named handler if
   * it exists, or the data for the only handler defined if no source key is
   * given.
   *
   * @param string $source_key
   *   The source key that should be used to retrieve data for a block.
   *
   * @return array|object
   *   A data array or object.
   */
  public function getData(string $source_key = 'data') {
    $query_handler = $this->getQueryHandler($source_key);
    if (!$query_handler) {
      return FALSE;
    }
    if (method_exists($this, 'alterEndpointQuery')) {
      $this->alterEndpointQuery($source_key, $query_handler);
    }
    return $query_handler->getData();
  }

  /**
   * Get the available keys for data sources of a block.
   */
  public function getSourceKeys() {
    $configuration = $this->getConfiguration();
    if (empty($configuration['data_sources'])) {
      return [];
    }
    return array_keys($configuration['data_sources']);
  }

  /**
   * Get the endpoints used for the block.
   *
   * @return array
   *   A list of full endpoint urls for a block.
   */
  public function getFullEndpointUrls() {
    $endpoints = [];
    $source_keys = $this->getSourceKeys();
    if (!empty($source_keys)) {
      foreach ($source_keys as $source_key) {
        $query_handler = $this->getQueryHandler($source_key);
        if (!$query_handler) {
          continue;
        }
        if (method_exists($this, 'alterEndpointQuery')) {
          $this->alterEndpointQuery($source_key, $query_handler);
        }
        $endpoints[] = $query_handler->getFullEndpointUrl();
      }
    }
    return $endpoints;
  }

}
