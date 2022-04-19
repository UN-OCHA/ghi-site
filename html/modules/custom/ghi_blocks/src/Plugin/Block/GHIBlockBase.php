<?php

namespace Drupal\ghi_blocks\Plugin\Block;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_blocks\Interfaces\AutomaticTitleBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OptionalTitleBlockInterface;
use Drupal\ghi_blocks\Traits\VerticalTabsTrait;
use Drupal\hpc_common\Plugin\HPCBlockBase;
use Drupal\layout_builder\Form\AddBlockForm;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Plugin\SectionStorage\SectionStorageBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for GHI blocks.
 *
 * By inheriting from HPCBlockBase, we get most of the necessary data retrieval
 * logic for block panes and also most of the context gathering logic.
 */
abstract class GHIBlockBase extends HPCBlockBase {

  use VerticalTabsTrait;

  /**
   * The default form key for the configuration form.
   */
  const DEFAULT_FORM_KEY = 'basic';

  /**
   * Current form state object if in a configuration context.
   *
   * @var \Drupal\Core\Form\FormStateInterface
   */
  protected $formState;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The context repository manager.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * Layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * The manager class for endpoint query plugins.
   *
   * @var \Drupal\hpc_api\Query\EndpointQueryManager
   */
  protected $endpointQueryManager;

  /**
   * The manager class for configuration container items.
   *
   * @var \Drupal\ghi_form_elements\ConfigurationContainerItemManager
   */
  protected $configurationContainerItemManager;

  /**
   * The section manager.
   *
   * @var \Drupal\ghi_sections\SectionManager
   */
  protected $sectionManager;

  /**
   * The selection criteria argument service.
   *
   * @var \Drupal\ghi_blocks\LayoutBuilder\SelectionCriteriaArgument
   */
  protected $selectionCriteriaArgument;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The controller resolver.
   *
   * @var \Drupal\Core\Controller\ControllerResolverInterface
   */
  protected $controllerResolver;

  /**
   * The route matcher.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Retrieves a configuration object.
   *
   * @param string $name
   *   The name of the configuration object to retrieve.
   *
   * @return \Drupal\Core\Config\Config
   *   A configuration object.
   */
  protected function config($name) {
    return $this->configFactory->get($name);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\ghi_blocks\Plugin\Block\GHIBlockBase $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    // Set our own properties.
    $instance->configFactory = $container->get('config.factory');
    $instance->contextRepository = $container->get('context.repository');
    $instance->layoutTempstoreRepository = $container->get('layout_builder.tempstore_repository');
    $instance->endpointQueryManager = $container->get('plugin.manager.endpoint_query_manager');
    $instance->configurationContainerItemManager = $container->get('plugin.manager.configuration_container_item_manager');
    $instance->sectionManager = $container->get('ghi_sections.manager');
    $instance->selectionCriteriaArgument = $container->get('ghi_blocks.layout_builder_edit_page.selection_criteria_argument');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->controllerResolver = $container->get('controller_resolver');
    $instance->routeMatch = $container->get('current_route_match');

    $instance->alterContexts();
    return $instance;
  }

  /**
   * Build the content of a GHI block.
   *
   * @return array
   *   Must return a render array.
   */
  abstract public function buildContent();

  /**
   * Provide block specific default configuration.
   */
  abstract protected function getConfigurationDefaults();

  /**
   * Get data for this block.
   *
   * @param string $source_key
   *   The source key that should be used to retrieve data for a block.
   *
   * @return array|object
   *   A data array or object.
   */
  public function getData(string $source_key = 'data') {
    $query = $this->getQueryHandler($source_key);
    return $query ? $query->getData() : NULL;
  }

  /**
   * Get a query handler for this block.
   *
   * This returns either the requested named handler if it exists, or the only
   * one defined if no source key is given.
   * The handler will be initialized with placeholders reflecting the current
   * contexts.
   *
   * @param string $source_key
   *   The source key that should be used to retrieve data for a block.
   *
   * @return \Drupal\hpc_api\Query\EndpointQueryPluginInterface
   *   The query handler class.
   */
  protected function getQueryHandler($source_key = 'data') {

    $configuration = $this->getPluginDefinition();
    if (empty($configuration['data_sources'])) {
      return NULL;
    }

    $sources = $configuration['data_sources'];
    $definition = !empty($sources[$source_key]) ? $sources[$source_key] : NULL;
    if (!$definition || !is_scalar($definition) || !$this->endpointQueryManager->hasDefinition($definition)) {
      return NULL;
    }

    /** @var \Drupal\hpc_api\Query\EndpointQueryPluginInterface $query_handler */
    $query_handler = $this->endpointQueryManager->createInstance($definition);

    // Get the available context values and use them as placeholder values for
    // the query.
    foreach ($this->getContexts() as $context_key => $context) {
      /** @var \Drupal\Core\Plugin\Context\Context $context */
      if ($context_key == 'node' || !$context->hasContextValue()) {
        continue;
      }
      $context_value = $context->getContextValue();

      if (is_scalar($context_value)) {
        // Arguments like "year".
        $query_handler->setPlaceholder($context_key, $context->getContextValue());
        continue;
      }
      elseif ($context_value instanceof ContentEntityInterface && $context_value->hasField('field_original_id')) {
        // Arguments like "plan_id".
        $original_id = $context_value->get('field_original_id')->value;
        if ($original_id && is_scalar($original_id)) {
          $query_handler->setPlaceholder($context_key . '_id', $original_id);
        }
      }
    }

    return $query_handler;
  }

  /**
   * {@inheritdoc}
   */
  protected function baseConfigurationDefaults() {
    return [
      'hpc' => $this->getConfigurationDefaults(),
    ] + parent::baseConfigurationDefaults();
  }

  /**
   * Get the configuration for a block instance.
   *
   * This returns only the configuration for a block plugin that is HPC
   * specific and additional to the default plugin configuration.
   *
   * @return array
   *   An array with configuration options specific to a block plugin instance.
   */
  protected function getBlockConfig() {
    if ($this->formState) {
      return $this->getTemporarySettings($this->formState);
    }
    return $this->configuration['hpc'];
  }

  /**
   * Set the HPC specific config for a block.
   *
   * @param array $config
   *   A config array.
   */
  protected function setBlockConfig(array $config) {
    $this->configuration['hpc'] = $config;
  }

  /**
   * Check if the block should display it's title.
   *
   * @return bool
   *   TRUE if a title can be shown, FALSE otherwise.
   */
  public function shouldDisplayTitle() {
    $plugin_definition = $this->getPluginDefinition();
    return !array_key_exists('title', $plugin_definition) || $plugin_definition['title'] !== FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitleSubform() {
    return self::DEFAULT_FORM_KEY;
  }

  /**
   * Check if the block has a default title.
   *
   * @return bool
   *   TRUE if a title can be shown, FALSE otherwise.
   */
  public function hasDefaultTitle() {
    $plugin_definition = $this->getPluginDefinition();
    return array_key_exists('default_title', $plugin_definition) && !empty($plugin_definition['default_title']);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $plugin_configuration = $this->getConfiguration();

    $build = [
      '#type' => 'container',
    ];

    if ($this->isHidden() && !$this->isPreview()) {
      // If the block is hidden and not in preview bail out.
      return [];
    }

    // Otherwise build the full block. First get the actual block content.
    $build_content = $this->buildContent();
    if (!$build_content) {
      return [];
    }

    // Handle the title display.
    if ($this->shouldDisplayTitle()) {
      if ($this instanceof AutomaticTitleBlockInterface) {
        $build['#title'] = $this->getAutomaticBlockTitle();
      }
      elseif ($this instanceof OptionalTitleBlockInterface) {
        $build['#title'] = $plugin_configuration['label'] ?? NULL;
      }
      elseif (!empty($build_content['#title'])) {
        $build['#title'] = $build_content['#title'];
        unset($build_content['#title']);
      }

      if (empty($plugin_configuration['label']) && $this->hasDefaultTitle()) {
        $plugin_definition = $this->getPluginDefinition();
        $build['#title'] = $plugin_definition['default_title'];
      }

      if (!empty($plugin_configuration['label_display']) && !array_key_exists('#title', $build)) {
        $build += [
          '#title' => $this->label(),
        ];
      }
      elseif (empty($plugin_configuration['label_display']) && array_key_exists('#title', $build)) {
        unset($build['#title']);
      }
    }

    if (!empty($build_content['#theme']) && $build_content['#theme'] == 'item_list') {
      $build_content['#context']['plugin_id'] = $this->getPluginId();
    }

    // Add the build content as a child.
    $build[] = $build_content;

    // Add some classes for styling.
    $build['#attributes']['class'][] = Html::getClass('ghi-block-' . $this->getPluginId());
    $build['#attributes']['class'][] = 'ghi-block';
    if ($this->getUuid()) {
      $build['#attributes']['class'][] = 'ghi-block-' . $this->getUuid();
    }
    // Add hidden classes.
    if ($this->isHidden()) {
      $build['#attributes']['class'][] = 'ghi-block--hidden';
      if ($this->isLayoutBuilder()) {
        // If we are here, the block is hidden and displayed inside the layout
        // builder interface. We want to inform the user that the block is
        // configured to be hidden..
        $build['#attributes']['class'][] = 'ghi-block--hidden-preview';
      }
    }

    // Allow the plugin to define attributes for it's wrapper.
    if (array_key_exists('#wrapper_attributes', $build_content)) {
      $build['#attributes'] = NestedArray::mergeDeep($build['#attributes'], $build_content['#wrapper_attributes']);
    }

    $build['#title_attributes']['class'][] = 'block-title';
    if (empty($build['#region'])) {
      $build['#region'] = $this->getRegion();
    }

    // Add the block instance to the render array, so that we have it available
    // in hooks.
    $build['#block_instance'] = $this;

    $build['#cache'] = [
      'contexts' => $this->getCacheContexts(),
      'tags' => $this->getCacheTags(),
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $cache_contexts = parent::getCacheContexts();
    $cache_contexts = Cache::mergeContexts($cache_contexts, [
      'url.path',
      'user',
    ]);
    return $cache_contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $cache_tags = parent::getCacheTags();
    $cache_tags = Cache::mergeTags($cache_tags, array_filter([
      $this->getPluginId() . ':' . $this->getUuid(),
    ]));
    if ($base_object = $this->getCurrentBaseObject()) {
      // Not sure where the context handling goes wrong, but for the moment we
      // have to add the base object cache tag manually to be sure that it's
      // always present.
      $cache_tags = Cache::mergeTags($cache_tags, $base_object->getCacheTags());
    }
    return $cache_tags;
  }

  /**
   * Form builder for the config form of simple block types.
   *
   * If a block is implementing the MultiStepFormBlockInterface, this method
   * does not need to be implemented. All other block plugins inheriting from
   * this base class need to implement the method.
   *
   * @param array $form
   *   An associative array containing the initial structure of the subform.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    $missing_class_message = sprintf('The plugin (%s) did not implement the getConfigForm() method.', $this->getPluginId());
    throw new PluginException($missing_class_message);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormValueFromFormState(FormStateInterface $form_state, $key) {
    // Extract the form values.
    // https://www.drupal.org/project/drupal/issues/2798261#comment-12735075
    $current_subform = $form_state->get('current_subform');

    $key = (array) $key;

    $step_values = NULL;
    $value_parents = array_merge([$current_subform], $key);

    if ($form_state instanceof SubformStateInterface) {
      $step_values = $form_state->getCompleteFormState()->cleanValues()->getValue($value_parents);
    }
    else {
      $step_values = $form_state->cleanValues()->getValue($value_parents);
    }

    if ($step_values) {
      return $step_values;
    }

    /** @var \Drupal\ghi_blocks\Plugin\Block\GHIBlockBase $block */
    $block = $form_state->get('block');
    $config = $block->getBlockConfig();

    $settings_key = $key;
    if ($block->isMultistepForm()) {
      $settings_key = array_merge([$current_subform], $settings_key);
    }
    return NestedArray::getValue($config, $settings_key);
  }

  /**
   * Checks if this block uses multi step forms for configuration.
   *
   * @return bool
   *   TRUE if the plugin implements MultiStepFormBlockInterface.
   */
  private function isMultistepForm() {
    return $this instanceof MultiStepFormBlockInterface;
  }

  /**
   * {@inheritdoc}
   */
  public function canShowSubform($form, FormStateInterface $form_state, $subform_key) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    parent::blockForm($form, $form_state);

    // Not sure why, but the preview toggles value is not available in the
    // blockElementSubmit callback, so we have to catch this here.
    if ($form_state->getTriggeringElement()) {
      $action = (string) end($form_state->getTriggeringElement()['#parents']);
      if ($action == 'preview') {
        // If the preview checkbox has been used, toggle the preview state.
        $preview = $form_state->get('preview');
        $form_state->set('preview', !$preview);
      }
    }

    $this->formState = $form_state;
    $form_state->addCleanValueKey('actions');
    $form_state->addCleanValueKey(['actions', 'subforms']);
    $form_state->addCleanValueKey(['actions', 'submit']);

    // Set contexts during the form building so that data can be retrieved.
    $this->setFormContexts($form_state);

    // Default is a simple form with a single configuration callback.
    $current_subform = self::DEFAULT_FORM_KEY;
    $form_callback = 'getConfigForm';

    if ($this->isMultistepForm()) {
      /** @var \Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface $this */
      $forms = $this->getSubforms();
      if (empty($forms)) {
        return $form;
      }

      $current_subform = $form_state->get('current_subform');
      if (!$current_subform || !array_key_exists($current_subform, $forms)) {
        $current_subform = $this->getDefaultSubform(!$this->getUuid());
      }
      $subform = $forms[$current_subform];
      $form_callback = $subform['callback'];

      if (!method_exists($this, $form_callback)) {
        return $form;
      }
    }

    // Set state. This is important.
    $form_state->set('current_subform', $current_subform);
    $form_state->set('block', $this);

    $form['#parents'] = [];
    $form['#array_parents'] = [];

    $wrapper_id = Html::getId('form-wrapper-ghi-block-config');

    // Set the parents and array parents.
    $array_parents = array_merge($form['#array_parents'], [
      'settings',
      'container',
    ]);
    $parents = $form['#parents'];
    $parents[] = $current_subform;
    if ($this->isMultistepForm()) {
      $array_parents[] = $current_subform;
    }

    // Prepare the subform.
    $form['container'] = [
      '#type' => 'container',
      // This is important for form processing and value submission.
      '#parents' => $parents,
      // Provide an anchor for AJAX, so that we know what to replace. See
      // Drupal\block\BlockForm::form for where that comes from.
      '#array_parents' => $array_parents,
      '#attributes' => [
        'id' => $wrapper_id,
        'class' => [Html::getClass('hpc-form-wrapper')],
      ],
    ];

    if (!$form_state->get('preview')) {
      if ($this->getTitleSubform() == $current_subform) {
        // Add the label widget to the form.
        $form['container']['label'] = $form['label'];
        $form['container']['label_display'] = $form['label_display'];

        $temporary_settings = $this->getTemporarySettings($form_state);
        if ($this instanceof MultiStepFormBlockInterface) {
          $form['container']['label']['#default_value'] = $temporary_settings[$this->getTitleSubform()]['label'] ?? $this->label();
          $form['container']['label_display']['#default_value'] = $temporary_settings[$this->getTitleSubform()]['label_display'] ?? $this->configuration['label_display'];
        }
        else {
          $form['container']['label']['#default_value'] = $temporary_settings['label'] ?? $this->label();
          $form['container']['label_display']['#default_value'] = $temporary_settings['label_display'] ?? $this->configuration['label_display'];
        }

        // Set the default values.
        $plugin_definition = $this->getPluginDefinition();
        if ($this->hasDefaultTitle() && (string) $form['container']['label']['#default_value'] == (string) $plugin_definition['admin_label']) {
          $form['container']['label']['#default_value'] = '';
        }
      }

      // And build the subform structure.
      $subform_state = SubformState::createForSubform($form['container'], $form, $form_state);
      $form['container'] += $this->{$form_callback}($form['container'], $subform_state);

      $this->processVerticalTabs($form['container'], $form_state);

      // Exclude buttons from submission values.
      $this->addButtonsToCleanValueKeys($form['container'], $form_state, $form['container']['#parents']);

      // Add after build callback for label handling.
      $form['#after_build'][] = [$this, 'blockFormAfterBuild'];
    }
    else {
      // Show a preview area.
      $temporary_settings = $this->getTemporarySettings($form_state);
      $label_subkey = $this instanceof MultiStepFormBlockInterface ? $this->getTitleSubform() : NULL;
      $this->configuration['hpc'] = $temporary_settings;
      $this->configuration['label'] = NestedArray::getValue($temporary_settings, array_filter([
        $label_subkey,
        'label',
      ]));
      $this->configuration['label_display'] = NestedArray::getValue($temporary_settings, array_filter([
        $label_subkey,
        'label_display',
      ]));
      $this->configuration['is_preview'] = TRUE;
      $build = $this->build();
      $form['container']['preview'] = [
        '#theme' => 'block',
        '#attributes' => [
          'data-block-preview' => $this->getPluginId(),
        ],
        '#configuration' => $this->configuration,
        '#base_plugin_id' => $this->getBaseId(),
        '#plugin_id' => $this->getPluginId(),
        '#derivative_plugin_id' => $this->getDerivativeId(),
        '#id' => $this->getPluginId(),
        '#attached' => [
          'library' => ['ghi_blocks/block.preview'],
        ],
        'content' => $build,
      ];
    }

    return $form;
  }

  /**
   * Check if a block is set to be hidden.
   *
   * @return bool
   *   TRUE if hidden, FALSE otherwise.
   */
  public function isHidden() {
    return !empty($this->configuration['visibility_status']) && $this->configuration['visibility_status'] == 'hidden';
  }

  /**
   * Check if a block is currently in preview.
   *
   * This can be either because it's previewed as part of the block
   * configuration, or because it's displayed in the Layout Builder interface,
   * which is some kind of preview too.
   *
   * @return bool
   *   TRUE if considered preview, FALSE otherwise.
   */
  protected function isPreview() {
    return $this->isConfigurationPreview() || $this->isLayoutBuilder();
  }

  /**
   * Check if a block is currently viewed inside the LayoutBuilder interface.
   *
   * @return bool
   *   TRUE if considered layout builder, FALSE otherwise.
   */
  protected function isLayoutBuilder() {
    return $this->routeMatch->getParameter('section_storage') instanceof SectionStorageBase;
  }

  /**
   * Check if a block is currently previewed in the configuration modal.
   *
   * @return bool
   *   TRUE if considered configuration preview, FALSE otherwise.
   */
  protected function isConfigurationPreview() {
    return !empty($this->configuration['is_preview']);
  }

  /**
   * Set the plugin contexts during form processing.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  private function setFormContexts(FormStateInterface $form_state) {
    // Provide context so that data can be retrieved.
    $build_info = $form_state->getBuildInfo();
    if (!empty($build_info['args']) && $build_info['args'][0] instanceof OverridesSectionStorage) {
      $section_storage = $build_info['args'][0];
      if ($section_storage->getContext('entity')) {
        try {
          $this->setContext('layout_builder.entity', $build_info['args'][0]->getContext('entity'));
          $this->alterContexts();
        }
        catch (ContextException $e) {
          // Fail silently.
        }
      }
    }
  }

  /**
   * Add all buttons recursively to the form state's clean value keys.
   *
   * This keeps the values array smaller and easier to debug.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $parents
   *   An array of parent elements.
   */
  protected function addButtonsToCleanValueKeys(array $form, FormStateInterface $form_state, array $parents = []) {
    $buttons = ['submit', 'button'];
    foreach (Element::children($form) as $element_key) {
      $element = $form[$element_key];
      if (array_key_exists('#type', $element) && in_array($element['#type'], $buttons)) {
        $form_state->addCleanValueKey(array_merge($parents, [$element_key]));
      }
      if (count(Element::children($element)) > 0) {
        $this->addButtonsToCleanValueKeys($element, $form_state, array_merge($parents, [$element_key]));
      }
    }
  }

  /**
   * After build callback for the block form.
   *
   * Remove the admin label input and handle blocks that should not have a
   * block.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The updated form array.
   */
  public function blockFormAfterBuild(array $form, FormStateInterface $form_state) {
    $plugin_definition = $this->getPluginDefinition();

    // Disable all of the default settings elements. We will handle them.
    $form['admin_label']['#access'] = FALSE;
    $form['admin_label']['#value'] = (string) $plugin_definition['admin_label'];
    $form['label']['#access'] = FALSE;
    $form['label']['#required'] = FALSE;
    $form['label_display']['#access'] = FALSE;
    $settings_form = &$form['container'];

    // Now manipulate the default settings elements according to our needs.
    if (array_key_exists('label', $settings_form)) {
      if ($this instanceof AutomaticTitleBlockInterface) {
        // This block plugin provides an automatic title, se we can safely hide
        // both the label field and the display checkbox.
        $settings_form['label']['#access'] = FALSE;
        $settings_form['label']['#required'] = FALSE;
        $settings_form['label_display']['#access'] = FALSE;
        $settings_form['label_display']['#value'] = TRUE;
        $settings_form['label_display']['#default_value'] = TRUE;
      }

      if ($this instanceof OptionalTitleBlockInterface) {
        // This label field is optional and the display toggle can be hidden.
        // Display status will be determined based on the presence of a title.
        $settings_form['label']['#required'] = FALSE;
        $settings_form['label']['#description'] = $this->t('You can set a title for this element. Leave empty to not use a title.');
        $settings_form['label_display']['#access'] = FALSE;
        $settings_form['label_display']['#value'] = TRUE;
        $settings_form['label_display']['#default_value'] = TRUE;
      }

      if ($this->hasDefaultTitle()) {
        // This block plugin provides a default title, so the label field is
        // optional and the display toggle can be hidden.
        $settings_form['label']['#required'] = FALSE;
        $settings_form['label']['#description'] = $this->t('Leave empty to use the default title %default_title.', [
          '%default_title' => $plugin_definition['default_title'],
        ]);
        $settings_form['label_display']['#access'] = FALSE;
      }

      if (!$this->shouldDisplayTitle()) {
        // This block plugin never shows a title, so we can hide the fields and
        // set the values directly.
        $settings_form['label']['#access'] = FALSE;
        $settings_form['label']['#value'] = (string) $plugin_definition['admin_label'];
        $settings_form['label_display']['#access'] = FALSE;
        $settings_form['label_display']['#value'] = FALSE;
      }
    }

    if (array_key_exists('year', $form['context_mapping'])) {
      $form['context_mapping']['year']['#access'] = FALSE;
      $form['context_mapping']['year']['#value'] = array_key_first($form['context_mapping']['year']['#options']);
    }

    if (array_key_exists('node', $form['context_mapping']) && array_key_exists('#options', $form['context_mapping']['node'])) {
      $options = array_keys($form['context_mapping']['node']['#options']);
      $options = array_filter($options);
      if (count($options) == 1) {
        $form['context_mapping']['node']['#access'] = FALSE;
        $form['context_mapping']['node']['#value'] = reset($options);
      }
    }
    return $form;
  }

  /**
   * Check if the given form state originates in a preview submit action.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state interface.
   *
   * @return bool
   *   TRUE if the form state has been created by a preview action, FALSE
   *   otherwise.
   */
  public function isPreviewSubmit(FormStateInterface $form_state) {
    $current_subform = $form_state->get('current_subform');
    $triggering_element = $form_state->getTriggeringElement();
    $action = end($triggering_element['#parents']);
    $values = $form_state->getValues();
    return $action == 'preview' && !array_key_exists($current_subform, $values);
  }

  /**
   * Element validate callback.
   *
   * @param array $element
   *   Form element array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state interface.
   */
  public function blockElementValidate(array &$element, FormStateInterface $form_state) {
    // Get the trigger.
    $triggering_element = $form_state->getTriggeringElement();

    // Get the subform key we have been on when the action was triggered.
    $current_subform = $form_state->get('current_subform');
    $action = end($triggering_element['#parents']);
    if (!in_array($action, ['submit', 'preview'])) {
      return;
    }

    // Get the values for that subform and.
    $step_values = $form_state->cleanValues()->getValue($current_subform ?? []);
    if ($step_values === NULL) {
      $form_state->setRebuild($action == 'preview');
      return;
    }

    if ($this instanceof OptionalTitleBlockInterface && $current_subform == $this->getTitleSubform()) {
      $step_values['label_display'] = !empty($step_values['label']);
    }

    if ($action == 'submit') {
      // For the final submit of the form, put the values into the form
      // storage of the current form, so that we have them available later.
      $form_state->set($current_subform, $step_values);
      return;
    }
    else {
      // Set the current step values for preview.
      $form_state->setValue($current_subform, $step_values);
      $form_state->set(['storage', $current_subform], $step_values);
      $form_state->setTemporaryValue($current_subform, $step_values);

      // Store the current tab, so that we can get back to it later.
      $this->processVerticalTabsSubmit($form_state->getCompleteForm()['settings']['container'], $form_state);
    }

    // Important to rebuild, otherwhise the preview won't update.
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {

    // This get's called when a submit button is clicked.
    if ($form_state->getTriggeringElement()['#parents'] != $form['actions']['submit']['#parents']) {
      // We only want to act on the real submit action for the full form.
      return;
    }

    // Get all submitted values.
    $values = $this->getTemporarySettings($form_state);

    // Because we handle the label fields, we also have to update the
    // configuration.
    $title_form_key = $this instanceof MultiStepFormBlockInterface ? $this->getTitleSubform() : NULL;
    $this->configuration['label'] = NestedArray::getValue($values, array_filter([
      $title_form_key,
      'label',
    ]));
    $this->configuration['label_display'] = NestedArray::getValue($values, array_filter([
      $title_form_key,
      'label_display',
    ]));

    // Remove traces of preview.
    unset($this->configuration['is_preview']);

    // Set the HPC specific block config.
    $this->setBlockConfig($values);

    if ($this instanceof AutomaticTitleBlockInterface || $this->hasDefaultTitle()) {
      // This is important to set, otherwise template_preprocess_block() will
      // hide the block title.
      $this->configuration['label_display'] = TRUE;
    }
    if ($this instanceof OptionalTitleBlockInterface) {
      $this->configuration['label_display'] = !empty($this->configuration['label']);
    }

    // Make sure that we have a UUID.
    $this->configuration['uuid'] = $this->getUuid();
  }

  /**
   * Custom form alter function for a block configuration.
   *
   * This get's called from ghi_blocks.module.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function blockFormAlter(array &$form, FormStateInterface $form_state) {

    $form['#attributes']['class'][] = Html::getClass($this->getPluginId());

    // Make sure the actions element is a container. GIN Layout Builder does
    // that already in the frontend, but when editing page manager pages in the
    // GIN backend theme, this is not done automatically.
    $form['actions']['#type'] = 'container';
    $form['actions']['#attributes']['class'][] = 'canvas-form__actions';

    // Also load our library to improve the UI.
    // @todo Check if this is sufficiently handled by ghi_blocks_form_alter().
    if (empty($form['#attached']['library'])) {
      $form['#attached']['library'] = [];
    }
    $form['#attached']['library'][] = 'ghi_blocks/layout_builder_modal_admin';

    $form['actions']['subforms'] = [
      '#type' => 'container',
      '#weight' => -1,
      '#attributes' => [
        'id' => Html::getId('ghi-layout-builder-subform-buttons'),
        'class' => ['ghi-layout-builder-subform-buttons'],
      ],
    ];

    $is_preview = $form_state->get('preview');

    $this->formState = $form_state;
    $this->setElementValidateOnAjaxElements($form['settings']['container']);

    if ($this->isMultistepForm()) {
      $forms = $this->getSubforms();
      $active_subform = $form_state->get('current_subform');
      foreach ($forms as $form_key => $subform) {
        $form['actions']['subforms'][$form_key] = [
          '#type' => 'submit',
          '#name' => $form_key . '-button',
          '#button_type' => $active_subform == $form_key ? 'primary' : 'secondary',
          '#value' => $subform['title'],
          '#element_submit' => [get_class($this) . '::ajaxMultiStepSubmit'],
          '#ajax' => [
            'callback' => [$this, 'navigateFormStep'],
            'wrapper' => $form['settings']['container']['#attributes']['id'],
            'effect' => 'fade',
            'method' => 'replace',
            'parents' => ['settings', 'container'],
          ],
          '#attributes' => [
            'class' => [$active_subform == $form_key ? 'active' : 'inactive'],
          ],
          '#disabled' => $is_preview || !$this->canShowSubform($form, $form_state, $form_key),
        ];
      }
    }

    $form['actions']['subforms']['preview'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preview'),
      '#name' => 'preview-toggle',
      // Note: This doesn't work. We manually add the checked attribute later.
      '#default_value' => (bool) $is_preview,
      '#ajax' => [
        'event' => 'change',
        'callback' => [$this, 'navigateFormStep'],
        'wrapper' => $form['settings']['container']['#attributes']['id'],
        'effect' => 'fade',
        'method' => 'replace',
        'parents' => ['settings', 'container'],
      ],
    ];
    if ($is_preview) {
      $form['actions']['subforms']['preview']['#attributes']['checked'] = 'checked';
    }

    // Add a cancel link.
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $this->routeMatch->getParameter('section_storage')->getLayoutBuilderUrl(),
      '#weight' => -1,
      '#attributes' => [
        'class' => [
          'dialog-cancel',
        ],
      ],
    ];

    if ($form_state->getBuildInfo()['callback_object'] instanceof AddBlockForm) {
      // For the add block form, kake this a link back to the block browser.
      $form['actions']['cancel']['#url'] = Url::fromRoute('layout_builder.choose_block', $this->routeMatch->getRawParameters()->all());
      $form['actions']['cancel']['#attributes']['class'][] = 'use-ajax';
    }

    $form['actions']['#weight'] = 99;

    // Set the element validate callback for all ajax enabled form elements.
    // This is needed so that the current form values will be stored in the
    // form and are therefor available for an immediate update of other
    // elements that might depend on the changed data.
    $this->setElementValidateOnAjaxElements($form['settings']['container']);
    $this->setElementValidateOnAjaxElements($form['actions']['subforms']['preview']);
  }

  /**
   * Recursively set the element validate property on ajax form elements.
   *
   * @param array $element
   *   The form element array.
   */
  private function setElementValidateOnAjaxElements(array &$element) {
    if (!empty($element['#ajax'])) {
      $element['#element_validate'][] = [$this, 'blockElementValidate'];
    }
    foreach (Element::children($element) as $element_key) {
      $this->setElementValidateOnAjaxElements($element[$element_key]);
    }
  }

  /**
   * Submit callback for multistep forms.
   *
   * @param array $element
   *   The element being submitted.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public static function ajaxMultiStepSubmit(array &$element, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#parents'];

    // Get the current subform.
    $current_subform = $form_state->get('current_subform');

    // Get the submitted values.
    $values = $form_state->cleanValues()->getValue($current_subform);
    // Put them into our form storage.
    if ($values !== NULL) {
      $form_state->set(['storage', $current_subform], $values);
    }

    $subforms = $form_state->get('block')->getSubforms();
    $requested_subform = array_key_exists('#next_step', $triggering_element) ? $triggering_element['#next_step'] : end($parents);
    if (array_key_exists($requested_subform, $subforms)) {
      // Update the current subform.
      $form_state->set('current_subform', $requested_subform);
    }

    // And make sure that we rebuild.
    $form_state->setRebuild(TRUE);
  }

  /**
   * Generic ajax callback to be used by implementing classes.
   *
   * @param array $form
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   *
   * @return array
   *   The part of the form structure that should be replaced.
   */
  public static function updateAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $triggering_element = $form_state->getTriggeringElement();
    $ajax = $triggering_element['#ajax'];

    if (!empty($ajax['wrapper']) && !empty($ajax['array_parents'])) {
      $wrapper_id = $ajax['wrapper'];
      $parents = $ajax['array_parents'];
      // Just update the full element.
      $response->addCommand(new ReplaceCommand('#' . $wrapper_id, NestedArray::getValue($form, $parents)));
    }

    return $response;
  }

  /**
   * Ajax callback to load new step.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state interface.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Ajax response.
   */
  public function navigateFormStep(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    if (empty($triggering_element['#ajax']['parents'])) {
      return $form;
    }

    // Update the requested section of the form.
    $parents = $triggering_element['#ajax']['parents'];
    $wrapper = $triggering_element['#ajax']['wrapper'];

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . $wrapper, NestedArray::getValue($form, $parents)));

    // And also update the custom subform buttons as they might be disabled.
    $button_wrapper = $form['actions']['subforms']['#attributes']['id'];
    $response->addCommand(new ReplaceCommand('#' . $button_wrapper, $form['actions']['subforms']));

    return $response;
  }

  /**
   * Get currently available temporary settings.
   *
   * This first looks in the storage of the form state object, then in the
   * submitted values and then as a last fallback in the current plugin
   * configuration.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   A configuration array for the plugin.
   */
  private function getTemporarySettings(FormStateInterface $form_state) {
    if ($form_state instanceof SubformStateInterface) {
      $values = $form_state->getCompleteFormState()->cleanValues()->getValues();
    }
    else {
      $values = $form_state->cleanValues()->getValues();
    }

    if ($this->isMultistepForm()) {
      /** @var \Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface $this */
      $subforms = $this->getSubforms();
      if (empty($subforms)) {
        return [];
      }
      // Get the stored subform values for each subform of this plugin.
      $settings = [];
      foreach (array_keys($subforms) as $form_key) {
        $storage_key = ['storage', $form_key];
        $temporary_values = $form_state->hasTemporaryValue($form_key) ? (array) $form_state->getTemporaryValue($form_key) : [];
        $storage_values = $form_state->has($storage_key) ? (array) $form_state->get($storage_key) : [];
        $submitted_values = !empty($values[$form_key]) ? $values[$form_key] : [];
        $settings[$form_key] = $temporary_values + $storage_values + $submitted_values;

        if (empty($settings[$form_key]) && !empty($this->configuration['hpc'][$form_key])) {
          $settings[$form_key] = $this->configuration['hpc'][$form_key];
        }
      }
    }
    else {
      $form_key = self::DEFAULT_FORM_KEY;
      $storage_key = ['storage', $form_key];
      // Put stored subform values into the settings for this plugin.
      // There are multiple places where these can be stored, so we look at
      // each of them in order.
      $temporary_values = $form_state->hasTemporaryValue($form_key) ? (array) $form_state->getTemporaryValue($form_key) : [];
      $storage_values = $form_state->has($storage_key) ? (array) $form_state->get($storage_key) : [];
      $submitted_values = !empty($values[$form_key]) ? $values[$form_key] : [];
      $settings = array_merge($temporary_values, $storage_values, $submitted_values);

      // If we still have nothing, we fall back to the existing configuration.
      if (empty($settings)) {
        $settings = $this->configuration['hpc'];
      }

      // Also make sure to persist everything to prevent issues when coming
      // back from preview.
      if (!empty($settings)) {
        $form_state->setTemporaryValue($form_key, $settings);
        $form_state->set($storage_key, $settings);
      }
    }
    return $settings;
  }

  /**
   * Get the current base entity.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The section node or NULL.
   */
  public function getCurrentBaseEntity($page_node = NULL) {
    // Get the section for the current page node.
    if ($page_node === NULL) {
      $page_node = $this->getPageNode();
    }
    if (!$page_node) {
      return NULL;
    }
    $base_entity = NULL;
    if ($page_node->hasField('field_entity_reference') && count($page_node->get('field_entity_reference')->referencedEntities()) == 1) {
      // The page node is a subpage of a section and references a section,
      // which references a base object.
      $entities = $page_node->get('field_entity_reference')->referencedEntities();
      $base_entity = reset($entities);
    }
    elseif ($page_node->hasField('field_base_object')) {
      // The page node is already a section node.
      $base_entity = $page_node;
    }
    return $base_entity;
  }

  /**
   * Get the base object for the current page context.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface|null
   *   A base object if it can be found.
   */
  public function getCurrentBaseObject() {
    foreach ($this->getContexts() as $context) {
      $context_definition = $context->getContextDefinition();
      if (!$context_definition instanceof EntityContextDefinition) {
        continue;
      }

      if (substr($context_definition->getDataType(), 7) == 'base_object') {
        return $context->getContextValue();
      }
      elseif ($context_definition->getDataType() == 'entity:node' && $entity = $context->getContextValue()) {
        /** @var \Drupal\Core\Entity\EntityInterface $entity */
        $section = $this->getCurrentBaseEntity($entity);
        if ($section) {
          return BaseObjectHelper::getBaseObjectFromNode($section);
        }
      }
    }
    return NULL;
  }

  /**
   * Get a plan id for the current page context.
   *
   * @return int|null
   *   A plan id if it can be found.
   */
  public function getCurrentBaseObjectId() {
    $base_object = $this->getCurrentBaseObject();
    if (!$base_object) {
      return NULL;
    }
    return $base_object->field_original_id->value;
  }

  /**
   * Get a plan id for the current page context.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface|null
   *   A plan object if it can be found.
   */
  public function getCurrentPlanObject() {
    if ($this->hasContext('plan')) {
      return $this->getContext('plan')->getContextValue();
    }
    return NULL;
  }

  /**
   * Get a plan id for the current page context.
   *
   * @return int|null
   *   A plan id if it can be found.
   */
  public function getCurrentPlanId() {
    $plan_object = $this->getCurrentPlanObject();
    if (!$plan_object) {
      return NULL;
    }
    return $plan_object->field_original_id->value;
  }

  /**
   * Get the specified named argument for the current page.
   *
   * This also checks whether the retrieved argument is a default value, in
   * which case it also checks with the selection criteria argument service to
   * see if a page argument can be extracted from the current request. This
   * would be the case when a page manager page, that is using layout builder,
   * is being edited.
   */
  protected function getPageArgument($key) {
    $context = $this->hasContext($key) ? $this->getContext($key) : NULL;
    return $context ? $context->getContextValue() : parent::getPageArgument($key);
  }

  /**
   * Alter the contexts for this plugin.
   */
  protected function alterContexts() {
    $contexts = $this->getContexts();
    $this->moduleHandler->alter('layout_builder_view_context', $contexts, $section_storage);
    foreach ($contexts as $context_name => $context) {
      if (in_array($context_name, ['node', 'entity', 'layout_builder.entity'])) {
        continue;
      }
      $this->setContext($context_name, $context);
    }
  }

}
