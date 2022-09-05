<?php

namespace Drupal\ghi_element_sync;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\NodeInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sync element service class.
 */
class SyncManager implements ContainerInjectionInterface {

  use MessengerTrait;
  use StringTranslationTrait;
  use DependencySerializationTrait;
  use LayoutEntityHelperTrait;

  /**
   * Define the supported base object types.
   */
  const BASE_OBJECT_TYPES_SUPPORTED = ['plan', 'governing_entity'];

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The UUID of the component.
   *
   * @var \Drupal\Component\UuidInterface
   */
  protected $uuid;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * Public constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, BlockManagerInterface $block_manager, Client $http_client, UuidInterface $uuid, TimeInterface $time, AccountProxyInterface $user, LayoutTempstoreRepositoryInterface $layout_tempstore_repository) {
    $this->config = $config_factory;
    $this->blockManager = $block_manager;
    $this->httpClient = $http_client;
    $this->uuidGenerator = $uuid;
    $this->time = $time;
    $this->currentUser = $user;
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.block'),
      $container->get('http_client'),
      $container->get('uuid'),
      $container->get('datetime.time'),
      $container->get('current_user'),
      $container->get('layout_builder.tempstore_repository')
    );
  }

  /**
   * Get a map that relates remote metadata with local field information.
   *
   * @return array
   *   A map array for metadata fields.
   */
  public function getMetadataFieldMap($metadata) {
    $map = [
      'shortname' => [
        'field' => 'field_short_name',
        'property' => 'value',
      ],
      'default_caseload' => [
        'field' => 'field_plan_caseload',
        'property' => 'attachment_id',
      ],
      'document_link' => [
        'field' => 'field_plan_document_link',
        'property' => 'url',
      ],
      'max_admin_level' => [
        'field' => 'field_max_admin_level',
        'property' => 'value',
      ],
      'decimal_format' => [
        'field' => 'field_decimal_format',
        'property' => 'value',
      ],
    ];
    $map['footnotes'] = [
      'field' => 'field_footnotes',
      'properties' => [],
    ];
    foreach (array_keys((array) $metadata->footnotes ?? []) as $property) {
      $map['footnotes']['properties'][] = $property;
    }
    return $map;
  }

  /**
   * Sync a node form a remote source.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which elements should be synced.
   * @param array $source_uuids
   *   Optionally limit to specific source uuids.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   An optional messenger to use for result messages.
   * @param bool $sync_elements
   *   Whether elements should be synced.
   * @param bool $sync_metadata
   *   Whether metadata should be synced.
   * @param bool $revisions
   *   Whether new revisions should be created.
   * @param bool $cleanup
   *   Whether existing elements should be cleaned up first.
   *
   * @return bool
   *   Indicating whether the node has been sucessfully processed or not.
   *
   * @throws SyncException
   *   When an error occurs.
   */
  public function syncNode(NodeInterface $node, array $source_uuids = NULL, MessengerInterface $messenger = NULL, $sync_elements = TRUE, $sync_metadata = TRUE, $revisions = FALSE, $cleanup = FALSE) {
    if ($messenger === NULL) {
      $messenger = $this->messenger();
    }

    $base_object = BaseObjectHelper::getBaseObjectFromNode($node);
    if (!in_array($base_object->bundle(), self::BASE_OBJECT_TYPES_SUPPORTED)) {
      return FALSE;
    }

    $remote_data = $this->getRemoteConfigurations($node);
    $sections = $this->getNodeSections($node);
    $delta = 0;

    if ($cleanup && $sections[$delta]->getComponents()) {
      foreach ($sections[$delta]->getComponents() as $component) {
        $sections[$delta]->removeComponent($component->getUuid());
      }
    }

    // For base objects of type plan, we also support the syncing of metadata.
    if ($base_object instanceof Plan && $sync_metadata) {
      $metadata = $remote_data->metadata;
      if ($metadata->status) {
        $node->setPublished();
      }
      else {
        $node->setUnpublished();
      }
      $field_map = $this->getMetadataFieldMap($metadata);
      foreach ($field_map as $remote_property => $local_def) {
        if ($remote_property == 'footnotes') {
          $base_object->field_footnotes = [];
          foreach ($local_def['properties'] as $footnote_property) {
            $base_object->field_footnotes[] = [
              'property' => $footnote_property,
              'footnote' => $metadata->{$remote_property}->{$footnote_property} ?? NULL,
            ];
          }
        }
        else {
          $base_object->{$local_def['field']}->{$local_def['property']} = $metadata->{$remote_property};
        }
      }

      // @codingStandardsIgnoreStart
      // $base_object->?? = $metadata->plan_version;
      // $base_object->?? = $metadata->status_string;
      // $base_object->?? = $metadata->prevent_fts_link;
      // @codingStandardsIgnoreEnd
      $base_object->save();
    }

    if ($sync_elements) {
      foreach ($remote_data->elements ?? [] as $element) {
        if (!$this->isSyncable($element)) {
          continue;
        }
        if ($source_uuids !== NULL && !in_array($element->uuid, $source_uuids)) {
          continue;
        }
        $definition = $this->getCorrespondingPluginDefintionForElement($element);
        $context_mapping = [
          'context_mapping' => array_intersect_key([
            'node' => 'layout_builder.entity',
          ], $definition['context_definitions']),
        ];
        $base_objects = BaseObjectHelper::getBaseObjectsFromNode($node);
        foreach ($base_objects as $_base_object) {
          if (!array_key_exists($_base_object->bundle(), $definition['context_definitions'])) {
            continue;
          }
          $context_mapping['context_mapping'][$_base_object->bundle()] = $_base_object->bundle() . '--' . $_base_object->getSourceId();
        }

        try {
          $mapped_config = $this->getMappedConfig($element, $node);
        }
        catch (IncompleteElementConfigurationException $e) {
          continue;
        }
        $existing_component = $this->getExistingSyncedComponent($node, $element);
        if ($existing_component) {
          // Update an existing component.
          $configuration = $mapped_config + $context_mapping + $existing_component->get('configuration');
          $existing_component->setConfiguration($configuration);
          $messenger->addMessage($this->t('Updated %plugin_title', [
            '%plugin_title' => $definition['admin_label'],
          ]), $messenger::TYPE_STATUS, TRUE);
        }
        else {
          // Append a new component.
          $messenger->addMessage($this->t('Added %plugin_title', [
            '%plugin_title' => $definition['admin_label'],
          ]), $messenger::TYPE_STATUS, TRUE);
          $config = array_filter([
            'id' => $definition['id'],
            'provider' => $definition['provider'],
            'data_sources' => $definition['data_sources'] ?? NULL,
            'sync' => [
              'source_uuid' => $element->uuid,
            ],
          ]) + $context_mapping;
          $config += $mapped_config;

          $component = new SectionComponent($this->uuidGenerator->generate(), 'content', $config);
          $sections[$delta]->appendComponent($component);
        }

      }
    }

    $node->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
    if ($revisions) {
      $node->setNewRevision(TRUE);
      $node->revision_log = $this->t('Synced page elements from @source_url', ['@source_url' => $this->getSyncSourceUrl()]);
      $node->setRevisionCreationTime($this->time->getRequestTime());
      $node->setRevisionUserId($this->currentUser->id());
    }
    $node->save();

    $this->layoutManagerDiscardChanges($node, $messenger);

    return TRUE;
  }

  /**
   * Remove previously synched elements from a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which elements should be synced.
   *
   * @return bool
   *   Indicating whether the node has been sucessfully reset or not.
   *
   * @throws SyncException
   *   When an error occurs.
   */
  public function resetNode(NodeInterface $node) {
    $base_object = BaseObjectHelper::getBaseObjectFromNode($node);
    if (!in_array($base_object->bundle(), self::BASE_OBJECT_TYPES_SUPPORTED)) {
      return FALSE;
    }

    $remote_data = $this->getRemoteConfigurations($node);
    $sections = $this->getNodeSections($node);
    $delta = 0;

    foreach ($remote_data->elements ?? [] as $element) {
      if (!$this->isSyncable($element)) {
        continue;
      }
      $existing_component = $this->getExistingSyncedComponent($node, $element);
      if ($existing_component) {
        $sections[$delta]->removeComponent($existing_component->getUuid());
      }
    }

    $this->layoutManagerDiscardChanges($node, $this->messenger());

    $node->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
    $node->setNewRevision(TRUE);
    $node->revision_log = $this->t('Removed previously synced page elements from @source_url', ['@source_url' => $this->getSyncSourceUrl()]);
    $node->setRevisionCreationTime($this->time->getRequestTime());
    $node->setRevisionUserId($this->currentUser->id());
    $node->save();

    return TRUE;
  }

  /**
   * Get all element configurations from the remote.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return object
   *   A configuration objects from the remote.
   *
   * @throws \Drupal\ghi_element_sync\SyncException
   *   Thrown when the remote source can't be reached or does not respond
   *   correctly.
   */
  public function getRemoteConfigurations(NodeInterface $node) {
    $settings = $this->getSettings();

    $base_object = BaseObjectHelper::getBaseObjectFromNode($node);

    $original_id = $base_object->field_original_id->value;
    $bundle = $base_object->bundle();

    if (empty($settings->get('sync_source'))) {
      throw new SyncException('Error: Source is not configured');
    }
    $source_url = rtrim($settings->get('sync_source'), '/');
    $url = $source_url . '/admin/hpc/plan-elements/export/' . $original_id . '/' . $bundle . '?time=' . microtime(TRUE);

    $headers = [];
    $basic_auth = $settings->get('basic_auth');
    if ($basic_auth && !empty($basic_auth['user']) && !empty($basic_auth['pass'])) {
      $headers['Authorization'] = 'Basic ' . base64_encode($basic_auth['user'] . ':' . $basic_auth['pass']);
    }

    $cookies = [
      'ghi_access' => $settings->get('access_key'),
    ];
    $jar = CookieJar::fromArray($cookies, parse_url($settings->get('sync_source'), PHP_URL_HOST));

    try {
      $response = $this->httpClient->request('GET', $url, [
        'headers' => $headers,
        'cookies' => $jar,
      ]);
    }
    catch (\Exception $e) {
      throw new SyncException($e->getMessage());
    }

    $code = $response->getStatusCode();
    if ($code != 200) {
      throw new SyncException('Error: Invalid response');
    }

    $body = $response->getBody()->getContents();
    if (empty($body)) {
      throw new SyncException('Error: Empty response');
    }

    $data = json_decode($body);
    if (empty($data->status)) {
      throw new SyncException('Error: Access key misconfigured or object not valid');
    }
    if (empty($data)) {
      // No error, but nothing to do either.
      return [];
    }
    return $data;
  }

  /**
   * Get the plugin that corresponds to the element from the remote system.
   *
   * @param object $element
   *   The element object from the remote.
   *
   * @return mixed
   *   A plugin definition, or NULL if the element type is invalid.
   */
  public function getCorrespondingPluginDefintionForElement($element) {
    $definitions = $this->blockManager->getDefinitions();
    if (!property_exists($element, 'type')) {
      return NULL;
    }
    if (isset($definitions[$element->type])) {
      return $definitions[$element->type];
    }
    // If there is no direct match, see if we can find a plugin that wants to
    // handle this element type.
    $definitions = array_filter($definitions, function ($definition) use ($element) {
      return array_key_exists('valid_source_elements', $definition) && in_array($element->type, $definition['valid_source_elements']);
    });
    return !empty($definitions) && count($definitions) == 1 ? reset($definitions) : NULL;
  }

  /**
   * Checks if the given element is syncable.
   *
   * @param object $element
   *   The element object from the remote.
   *
   * @return bool
   *   Whether the element is syncable.
   */
  public function isSyncable($element) {
    $definition = $this->getCorrespondingPluginDefintionForElement($element);
    if (!$definition) {
      return FALSE;
    }
    $class = $definition['class'];
    return in_array('Drupal\ghi_element_sync\SyncableBlockInterface', class_implements($class));
  }

  /**
   * Checks if the given element has a valid source that allows synching.
   *
   * @param object $element
   *   The element object from the remote.
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return bool
   *   Whether the element has a valid source.
   */
  public function hasValidSource($element, NodeInterface $node) {
    if (!$this->isSyncable($element)) {
      return FALSE;
    }
    try {
      $mapped_config = $this->getMappedConfig($element, $node, TRUE);
    }
    catch (IncompleteElementConfigurationException $e) {
      return FALSE;
    }
    return !empty($mapped_config);
  }

  /**
   * Get the current sync status.
   *
   * @param object $element
   *   The element object from the remote.
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return string
   *   A string representing the sync status.
   */
  public function getSyncStatus($element, NodeInterface $node) {
    if (!$this->isSyncable($element)) {
      return '';
    }
    $existing_component = $this->getExistingSyncedComponent($node, $element);
    if (!$existing_component) {
      return $this->t('Not synced');
    }
    try {
      $mapped_config = $this->getMappedConfig($element, $node, TRUE);
    }
    catch (IncompleteElementConfigurationException $e) {
      $mapped_config = NULL;
    }
    if ($mapped_config === NULL) {
      return $this->t('Invalid source');
    }
    $remote_hash = md5(serialize($mapped_config['hpc']));
    $local_hash = md5(serialize($existing_component->get('configuration')['hpc']));
    return $remote_hash == $local_hash ? $this->t('In sync') : $this->t('Changed');
  }

  /**
   * Get the mapped config for the given element.
   *
   * @param object $element
   *   The source element.
   * @param \Drupal\node\NodeInterface $node
   *   The node object that is the sync target.
   * @param bool $dry_run
   *   Whether this is a test or a real mapping.
   *
   * @return array
   *   A mapped config array.
   *
   * @throws \Drupal\ghi_element_sync\IncompleteElementConfigurationException;
   */
  private function getMappedConfig($element, NodeInterface $node, $dry_run = FALSE) {
    $definition = $this->getCorrespondingPluginDefintionForElement($element);
    if (!$definition) {
      return NULL;
    }
    $class = $definition['class'];
    $config = json_decode(json_encode($element->configuration));

    $common_config = [];
    $widget_options = $config->widget_options ?? NULL;
    $widget_visibility = $widget_options ? ($widget_options->widget_visibility ?? NULL) : NULL;
    if (!empty($widget_visibility) && $widget_visibility != 'public') {
      $common_config['visibility_status'] = 'hidden';
    }
    return $class::mapConfig($config, $node, $element->type, $dry_run) + $common_config;
  }

  /**
   * Find a section component corresponding to the given source element.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   * @param object $element
   *   The configuration object from the sync source.
   *
   * @return \Drupal\layout_builder\SectionComponent|null
   *   Either a matching component, or NULL.
   */
  private function getExistingSyncedComponent(NodeInterface $node, $element) {
    $section_storage = $this->getSectionStorageForEntity($node);
    $sections = $section_storage->getSections();
    foreach ($sections[0]->getComponents() as $component) {
      $configuration = $component->get('configuration');
      if (!empty($configuration['sync']) && !empty($configuration['sync']['source_uuid']) && $configuration['sync']['source_uuid'] == $element->uuid) {
        return $component;
      }
    }
    return NULL;
  }

  /**
   * Get sections for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Drupal\layout_builder\Section[]
   *   An array of layout builder sections.
   */
  private function getNodeSections(NodeInterface $node) {
    $section_storage = $this->getSectionStorageForEntity($node);
    if (!$section_storage) {
      return NULL;
    }
    $sections = $section_storage->getSections();
    return $sections;
  }

  /**
   * Clear layout builders shared temp store.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which elements should be synced.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   A messenger to use for result messages.
   */
  private function layoutManagerDiscardChanges(NodeInterface $node, MessengerInterface $messenger) {
    $section_storage = $this->getSectionStorageForEntity($node);
    // @todo See if the view mode can be retrieved somehow.
    $section_storage->setContextValue('view_mode', 'default');
    $this->layoutTempstoreRepository->delete($section_storage);
    $messenger->addMessage($this->t('Cleared layout builder temporary storage'));
  }

  /**
   * Get the sync settings.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   A settings object.
   */
  private function getSettings() {
    return $this->config->get('ghi_element_sync.settings');
  }

  /**
   * Get the sync source URL.
   *
   * @return string
   *   The sync source url.
   */
  public function getSyncSourceUrl() {
    return $this->getSettings()->get('sync_source');
  }

  /**
   * Get the available node types for syncing.
   *
   * @return array
   *   An array of node type names.
   */
  public function getAvailableNodeTypes() {
    return $this->getSettings()->get('node_types') ?? [];
  }

}
