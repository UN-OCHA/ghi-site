<?php

namespace Drupal\ghi_blocks\Plugin\Block;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\ghi_base_objects\Entity\BaseObjectAwareEntityInterface;
use Drupal\ghi_base_objects\Entity\BaseObjectChildInterface;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_blocks\Form\ImportBlockForm;
use Drupal\ghi_blocks\Interfaces\AutomaticTitleBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OptionalTitleBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Traits\VerticalTabsTrait;
use Drupal\ghi_plan_clusters\Entity\PlanCluster;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Drupal\hpc_api\Helpers\ProfileHelper;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\hpc_common\Helpers\BlockHelper;
use Drupal\hpc_common\Plugin\HPCBlockBase;
use Drupal\hpc_downloads\DownloadSource\BlockSource;
use Drupal\hpc_downloads\Interfaces\HPCDownloadExcelInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPDFInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPNGInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPluginInterface;
use Drupal\layout_builder\Form\AddBlockForm;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Plugin\SectionStorage\SectionStorageBase;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\node\NodeInterface;
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
   * The form key for the base object form.
   */
  const CONTEXTS_FORM_KEY = 'contexts';

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
   * The form submitter service.
   *
   * @var \Drupal\Core\Form\FormSubmitter
   */
  protected $formSubmitter;

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
    $instance->configurationContainerItemManager = $container->get('plugin.manager.configuration_container_item_manager');
    $instance->sectionManager = $container->get('ghi_sections.manager');
    $instance->selectionCriteriaArgument = $container->get('ghi_blocks.layout_builder_edit_page.selection_criteria_argument');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->controllerResolver = $container->get('controller_resolver');
    $instance->routeMatch = $container->get('current_route_match');
    $instance->formSubmitter = $container->get('form_submitter');

    $instance->getContexts();

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
   * {@inheritdoc}
   */
  protected function baseConfigurationDefaults() {
    return [
      'hpc' => $this->getConfigurationDefaults(),
      'contexts' => [
        'data_object' => NULL,
        'context_mapping' => [],
      ],
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
  public function getBlockConfig() {
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
   * Set the form state for the current block instance.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function setFormState(FormStateInterface $form_state) {
    $this->formState = $form_state;
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
    return !empty($this->getDefaultTitle());
  }

  /**
   * Get the default title.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The default title if one is set in the plugin definition.
   */
  public function getDefaultTitle() {
    $plugin_definition = $this->getPluginDefinition();
    if (empty($plugin_definition['default_title'])) {
      return NULL;
    }
    return $plugin_definition['default_title'];
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $label = parent::label();
    $plugin_configuration = $this->getConfiguration();
    $configured_label = $plugin_configuration['label'] ?? NULL;
    if ($this instanceof AutomaticTitleBlockInterface) {
      return $this->getAutomaticBlockTitle();
    }
    elseif ($this instanceof OptionalTitleBlockInterface) {
      return $configured_label != '<none>' ? $configured_label : '';
    }
    elseif ($this instanceof OverrideDefaultTitleBlockInterface) {
      $default_title = $configured_label && $configured_label != '<none>' ? $configured_label : $this->getDefaultTitle();
      return $default_title != '<none>' ? $default_title : '';
    }
    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviewFallbackString() {
    $label = parent::label();
    if ($label == '<none>') {
      $definition = $this->getPluginDefinition();
      $label = (string) $definition['admin_label'];
    }
    return $this->t('"@block" block', ['@block' => $label]);
  }

  /**
   * Get the block id.
   *
   * @return string
   *   A unique ID for this block.
   */
  protected function getBlockId() {
    $block_id = drupal_static(__FUNCTION__ . '_' . $this->getUuid(), Html::getId('block-' . $this->getUuid()));
    return $block_id;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $plugin_configuration = $this->getConfiguration();

    $build = [
      '#theme_wrappers' => [
        'container' => [
          '#attributes' => ['class' => ['block-content']],
        ],
      ],
    ];

    if ($this->isHidden() && !$this->isPreview()) {
      // If the block is hidden and not in preview bail out.
      return [];
    }

    // Otherwise build the full block. First get the actual block content.
    $profile_key = ProfileHelper::profileStart(static::class . ':buildContent');
    $build_content = $this->buildContent();
    ProfileHelper::profileEnd($profile_key);
    if (!$build_content) {
      return $build_content ?? [];
    }

    // Handle the title display.
    // @todo This is confusing and needs cleanup.
    if ($this->shouldDisplayTitle() && empty($build_content['#title_processed'])) {
      $build['#title'] = $this->label();
      $display_label = $this->configuration['label_display'] ?? FALSE;
      if ($this instanceof AutomaticTitleBlockInterface || $this instanceof OverrideDefaultTitleBlockInterface) {
        $display_label = TRUE;
      }
      elseif (!empty($build_content['#title'])) {
        $build['#title'] = $build_content['#title'];
        unset($build_content['#title']);
      }

      if (!$display_label) {
        unset($build['#title']);
      }
      $this->configuration['label_display'] = $display_label;
    }

    if (!empty($build_content['#theme']) && $build_content['#theme'] == 'item_list') {
      $build_content['#context']['plugin_id'] = $this->getPluginId();
    }

    // Add the build content as a child. We make sure that the final $build
    // always has proper element children instead of direct render arrays.
    if (!count(Element::children($build_content))) {
      $build[] = $build_content;
    }
    else {
      $build += $build_content;
    }

    // Add some classes for styling.
    $build['#attributes']['id'] = $this->getBlockId();
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

    // Allow the plugin to define additional attributes for the block itself.
    if (array_key_exists('#block_attributes', $build_content)) {
      $build['#attributes'] = NestedArray::mergeDeep($build['#attributes'], $build_content['#block_attributes']);
    }

    // Allow the plugin to define attributes for it's wrapper.
    if (array_key_exists('#wrapper_attributes', $build_content)) {
      $build['#theme_wrappers']['container']['#attributes'] = NestedArray::mergeDeep($build['#theme_wrappers']['container']['#attributes'], $build_content['#wrapper_attributes']);
    }

    $build['#title_attributes']['class'][] = 'block-title';
    if (empty($build['#region'])) {
      $build['#region'] = $this->getRegion();
    }

    // Prepare action links.
    $download_links = !empty($build['#download_links']) ? $build['#download_links'] : [];
    if ($this instanceof HPCDownloadPluginInterface && !empty($plugin_configuration['uuid'])) {
      $download_types = $this->getAvailableDownloadTypes();
      if (!empty($download_types) && $this->getDownloadSource()) {
        /** @var \Drupal\hpc_downloads\DownloadDialog\DownloadDialogPlugin $download_dialog */
        $download_dialog = \Drupal::service('hpc_downloads.download_dialog_plugin');
        $download_links[] = $download_dialog->buildDialogLink($this, $this->t('Downloads'));
      }
    }

    if (!empty($download_links)) {
      $build['#download_links'] = $download_links;
    }

    // Add the block instance to the render array, so that we have it available
    // in hooks.
    $build['#block_instance'] = $this;

    // Set the cache properties, merge in anything that the builder might have
    // set.
    $build['#cache'] = [
      'contexts' => Cache::mergeContexts($this->getCacheContexts(), $build_content['#cache']['contexts'] ?? []),
      'tags' => Cache::mergeTags($this->getCacheTags(), $build_content['#cache']['tags'] ?? []),
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
      'url.query_args',
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
    return ($this instanceof MultiStepFormBlockInterface);
  }

  /**
   * {@inheritdoc}
   */
  public function canShowSubform(array $form, FormStateInterface $form_state, $subform_key) {
    return TRUE;
  }

  /**
   * See if this block needs to configure the data object it works with.
   *
   * @return bool
   *   TRUE if the block needs a base object, FALSE if it already has one.
   */
  public function needsContextConfiguration() {
    /** @var \Drupal\ghi_blocks\Plugin\Block\GHIBlockBase $instance */
    $instance = $this->formState->get('block') ?? $this;
    if ($instance->canSelectBaseObject() && !$instance->getSelectedDataObjectId()) {
      return TRUE;
    }
    return !empty($instance->getContextMapping()) && empty($instance->getCurrentBaseObject());
  }

  /**
   * See if the block allows to select the base object it works with.
   *
   * This is the case if more than one base object is available.
   *
   * @return bool
   *   TRUE if the blocks base object can be selected, FALSE otherwise.
   */
  public function canSelectBaseObject() {
    if ($this->getPageNode() instanceof PlanCluster) {
      // This prevents the context form to be visible on cluster subpages, as
      // these do have both the governing entity object and the plan object
      // available.
      return FALSE;
    }
    $base_objects_per_bundle = $this->getBaseObjectsPerBundle();
    $count_objects = array_reduce($base_objects_per_bundle, function ($carry, $item) {
      return count($item) + $carry;
    });
    return $count_objects > 1;
  }

  /**
   * Get the available base objects per bundle.
   *
   * @return array
   *   An array of base object bundles, with the values being an array of base
   *   object ids.
   */
  private function getBaseObjectsPerBundle() {
    $instance = $this->formState?->get('block') ?? $this;
    $base_objects_per_bundle = [];

    // Get the expected base object types. This assumes that the context
    // definition contains a constraint on the base object bundle.
    $expected_base_object_types = array_filter(array_map(function ($definition) {
      /** @var \Drupal\Core\Plugin\Context\ContextDefinitionInterface $definition */
      $data_type = $definition->getDataType();
      if (strpos($data_type, 'entity:') !== 0) {
        return NULL;
      }
      [, $entity_type_id] = explode(':', $data_type);
      if ($entity_type_id != 'base_object') {
        return NULL;
      }
      return $definition->getConstraint('Bundle');
    }, $this->getContextDefinitions()));

    foreach ($instance->getContexts() as $context) {
      if (!$context->hasContextValue()) {
        continue;
      }
      if (!$context->getContextValue() instanceof BaseObjectInterface) {
        continue;
      }
      $base_object = $context->getContextValue();
      if (!in_array($base_object->bundle(), $expected_base_object_types)) {
        // The block does not need this kind of object.
        continue;
      }
      $base_objects_per_bundle[$base_object->bundle()] = $base_objects_per_bundle[$base_object->bundle()] ?? [];
      $base_objects_per_bundle[$base_object->bundle()][$base_object->id()] = $base_object->id();
    }
    return $base_objects_per_bundle;
  }

  /**
   * The context select form callback.
   *
   * See self::blockFormAlter() for changes to the context_mapping.
   */
  protected function contextForm($form, FormStateInterface $form_state) {
    $message = $this->t('There are multiple context objects available on the current page. In order for this element to function correctly, you must select which object to use to retrieve the underlying data from the data source.');
    $form['message'] = [
      '#type' => 'markup',
      '#markup' => new FormattableMarkup('<p>@message</p>', ['@message' => $message]),
    ];

    $base_objects_per_bundle = $this->getBaseObjectsPerBundle();
    $options = [];
    foreach ($base_objects_per_bundle as $ids) {
      $objects = $this->entityTypeManager->getStorage('base_object')->loadMultiple($ids);
      foreach ($objects as $object) {
        $bundle = $object->type->entity->label();
        $options[$bundle] = $options[$bundle] ?? [];
        $options[$bundle][$object->id()] = (string) ($object instanceof BaseObjectChildInterface ? $object->labelWithParent() : $object->label());
      }
    }
    $form['data_object'] = [
      '#type' => 'select',
      '#title' => $this->t('Data object'),
      '#options' => $options,
      '#default_value' => $this->getSelectedDataObjectId(),
      '#access' => count($options) > 0,
    ];
    return $form;
  }

  /**
   * Get the configuration subforms available to this block.
   *
   * @return array
   *   An array of subforms, keyed by the machine name, values are arrays with
   *   title" and "callback" keys that hold strings.
   */
  public function getSubforms() {
    $subforms = &drupal_static(__FUNCTION__, NULL);
    if ($subforms === NULL) {
      $definition = $this->getPluginDefinition();
      $plugin_subforms = $definition['config_forms'] ?? [
        self::DEFAULT_FORM_KEY => [
          'title' => $this->t('Configuration'),
          'callback' => 'getConfigForm',
        ],
      ];
      $subforms = [];
      if ($this->canSelectBaseObject()) {
        $subforms[self::CONTEXTS_FORM_KEY] = [
          'title' => $this->t('Context'),
          'callback' => 'contextForm',
        ];
      }
      $subforms = $subforms + $plugin_subforms;
    }
    return $subforms;
  }

  /**
   * Get the wrapper id for the config container.
   *
   * @return string
   *   An id string.
   */
  protected function getContainerWrapper() {
    return Html::getId('form-wrapper-ghi-block-config');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $plugin_definition = $this->getPluginDefinition();

    // Disable all of the default settings elements. We will handle them.
    $form['admin_label']['#access'] = FALSE;
    $form['admin_label']['#value'] = (string) $plugin_definition['admin_label'];
    $form['label']['#default_value'] = $form['label']['#default_value'] ?: '<none>';
    $form['label']['#access'] = FALSE;
    $form['label']['#required'] = FALSE;
    $form['label_display']['#access'] = FALSE;
    $form['context_mapping']['#access'] = $form_state->get('current_subform') == self::CONTEXTS_FORM_KEY && !$this->isConfigurationPreview();

    $settings_form = &$form['container'];

    $settings_form['context_mapping']['#access'] = $form_state->get('current_subform') == self::CONTEXTS_FORM_KEY;

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

      if ($this instanceof OptionalTitleBlockInterface || $this instanceof OverrideDefaultTitleBlockInterface) {
        // This label field is optional and the display toggle can be hidden.
        // Display status will be determined based on the presence of a title.
        $settings_form['label']['#default_value'] = $settings_form['label']['#default_value'] == '<none>' ? '' : $settings_form['label']['#default_value'];
        $settings_form['label']['#required'] = FALSE;
        $settings_form['label']['#description'] = $this->t('You can set a title for this element. Leave empty to not use a title.');
        $settings_form['label_display']['#access'] = FALSE;
        $settings_form['label_display']['#value'] = TRUE;
        $settings_form['label_display']['#default_value'] = TRUE;
      }

      if ($this instanceof OverrideDefaultTitleBlockInterface || $this->hasDefaultTitle()) {
        // This block plugin provides a default title, so the label field is
        // optional and the display toggle can be hidden.
        $settings_form['label']['#default_value'] = $settings_form['label']['#default_value'] == '<none>' ? '' : $settings_form['label']['#default_value'];
        if ($settings_form['label']['#default_value'] == $this->getDefaultTitle()) {
          $settings_form['label']['#default_value'] = '';
        }
        $settings_form['label']['#required'] = FALSE;
        $settings_form['label']['#description'] = $this->t('Leave empty to use the default title "%default_title".', [
          '%default_title' => $this->getDefaultTitle(),
        ]);
        $settings_form['label']['#placeholder'] = $this->getDefaultTitle();
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

    if (array_key_exists('year', $form['context_mapping']) && $form['context_mapping']['year']['#type'] == 'select') {
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
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    parent::blockForm($form, $form_state);
    $form['#ghi_modal_form'] = TRUE;

    $form_state->set('block', $this);
    $this->setFormState($form_state);

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

    $form_state->addCleanValueKey('actions');
    $form_state->addCleanValueKey(['actions', 'subforms']);
    $form_state->addCleanValueKey(['actions', 'submit']);

    // Set contexts during the form building so that data can be retrieved.
    $this->setFormContexts($form_state);

    // Default is a simple form with a single configuration callback.
    $current_subform = self::DEFAULT_FORM_KEY;
    $form_callback = 'getConfigForm';

    // Get the available subforms.
    $forms = $this->getSubforms();

    // If this block needs to select the base object, that's really the first
    // thing to do.
    $show_context_form = $this->needsContextConfiguration() || $form_state->get('current_subform') == self::CONTEXTS_FORM_KEY;
    if ($show_context_form && array_key_exists(self::CONTEXTS_FORM_KEY, $forms)) {
      $subform = $forms[self::CONTEXTS_FORM_KEY];
      $current_subform = self::CONTEXTS_FORM_KEY;
      $form_callback = $subform['callback'];
    }
    elseif ($this->isMultistepForm()) {
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

    $form['#parents'] = [];
    $form['#array_parents'] = [];

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
        'id' => $this->getContainerWrapper(),
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
          $configured_label = $temporary_settings[$this->getTitleSubform()]['label'] ?? NULL;
          if ($this instanceof OptionalTitleBlockInterface) {
            $form['container']['label']['#default_value'] = $configured_label ?? '<none>';
            $form['container']['label_display']['#default_value'] = !empty($configured_label);
          }
          else {
            $form['container']['label']['#default_value'] = $configured_label ?? $this->label();
            $form['container']['label_display']['#default_value'] = $temporary_settings[$this->getTitleSubform()]['label_display'] ?? $this->configuration['label_display'];
          }
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
    }
    else {
      // Show a preview area.
      $temporary_settings = $this->getTemporarySettings($form_state);
      $label_subkey = $this instanceof MultiStepFormBlockInterface ? $this->getTitleSubform() : NULL;
      $this->configuration['hpc'] = $temporary_settings;
      $this->configuration['label'] = NestedArray::getValue($temporary_settings, array_filter([
        $label_subkey,
        'label',
      ])) ?? $this->label();
      $this->configuration['label_display'] = NestedArray::getValue($temporary_settings, array_filter([
        $label_subkey,
        'label_display',
      ]));
      $this->configuration['is_preview'] = TRUE;
      $build = $this->build();
      $form['container']['preview'] = $build ? [
        '#theme' => 'block',
        '#attributes' => [
          'data-block-preview' => $this->getPluginId(),
        ] + ($build['#attributes'] ?? []),
        '#configuration' => $this->configuration,
        '#base_plugin_id' => $this->getBaseId(),
        '#plugin_id' => $this->getPluginId(),
        '#derivative_plugin_id' => $this->getDerivativeId(),
        '#id' => $this->getPluginId(),
        '#attached' => [
          'library' => ['ghi_blocks/block.preview'],
        ],
        'content' => $build,
      ] : NULL;
    }

    return $form;
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

    if ($contexts = $form_state->getValue(self::CONTEXTS_FORM_KEY)) {
      $form_state->setValue('context_mapping', $contexts['context_mapping']);
    }

    $values = $form_state->getValues();

    if (array_key_exists(self::CONTEXTS_FORM_KEY, $values)) {
      // Store the selected data object in the form state. This will be
      // persisted later in self::blockSubmit().
      if (array_key_exists('data_object', $values[self::CONTEXTS_FORM_KEY])) {
        $data_object = $values[self::CONTEXTS_FORM_KEY]['data_object'] ?? NULL;
        $form_state->set('data_object', $data_object);
      }

      // Update and store the context mapping in the form state. This will be
      // persisted later in self::blockSubmit().
      $context_mapping = $values[self::CONTEXTS_FORM_KEY]['context_mapping'] ?? $this->configuration['context_mapping'];
      if ($base_object = $this->getSelectedDataObject()) {
        // Update the context mapping based on the selected data object.
        $this->updateContextMapping($context_mapping, $base_object);
      }
      $form_state->setValue('context_mapping', $context_mapping);
      unset($values[self::CONTEXTS_FORM_KEY]['context_mapping']);
    }

    if (!in_array($action, ['submit', 'preview'])) {
      return;
    }

    // Get the values for that subform and.
    $step_values = $form_state->cleanValues()->getValue($current_subform ?? []) ?? [];
    // Also get all other values formerly stored and fill in the blanks.
    $values = $this->getTemporarySettings($form_state);
    $step_values += $values[$current_subform] ?? [];

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
      $form_state->setTemporaryValue($current_subform, $step_values);
      return;
    }
    elseif ($action == 'preview' && $triggering_element['#default_value'] === FALSE) {
      // Set the current step values for preview, but only when entering into
      // preview. When leaving preview, we do not want to update the stored
      // values because there will be no configuration changes submitted.
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
    $submitted_label = NestedArray::getValue($values, array_filter([
      $title_form_key,
      'label',
    ]));
    $submitted_label_display = NestedArray::getValue($values, array_filter([
      $title_form_key,
      'label_display',
    ]));
    $this->configuration['label'] = $submitted_label ?? $this->configuration['label'];
    $this->configuration['label_display'] = $submitted_label_display ?? $this->configuration['label_display'];

    if ($this instanceof OptionalTitleBlockInterface && empty($this->configuration['label'])) {
      $this->configuration['label'] = '<none>';
    }
    if ($this instanceof OverrideDefaultTitleBlockInterface && empty($this->configuration['label'])) {
      $this->configuration['label'] = $this->getDefaultTitle();
    }

    // Remove traces of preview.
    unset($this->configuration['is_preview']);

    // Set the HPC specific block config.
    $this->setBlockConfig($values);

    if ($this instanceof AutomaticTitleBlockInterface || $this instanceof OverrideDefaultTitleBlockInterface || $this->hasDefaultTitle()) {
      // This is important to set, otherwise template_preprocess_block() will
      // hide the block title.
      $this->configuration['label_display'] = TRUE;
    }
    if ($this instanceof OptionalTitleBlockInterface) {
      $this->configuration['label_display'] = !empty($this->configuration['label']);
    }

    // Persist the selected data object.
    if ($base_object = $this->getSelectedDataObject()) {
      $this->configuration[self::CONTEXTS_FORM_KEY]['data_object'] = $base_object->id();

      if ($base_object = $this->getSelectedDataObject()) {
        // Update the context mapping based on the selected data object.
        $context_mapping = $this->getContextMapping();
        $this->updateContextMapping($context_mapping, $base_object);
        $this->setContextMapping($context_mapping);
        $this->configuration[self::CONTEXTS_FORM_KEY]['context_mapping'] = $context_mapping;
      }
    }

    // Make sure that we have a UUID.
    $this->configuration['uuid'] = $this->getUuid();
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
    return $this->isConfigurationPreview() || ($this->isLayoutBuilder() && !$this->isLayoutBuilderFormSubmission());
  }

  /**
   * Check if a block is currently viewed inside the LayoutBuilder interface.
   *
   * @return bool
   *   TRUE if considered layout builder, FALSE otherwise.
   */
  public function isLayoutBuilder() {
    return $this->routeMatch->getParameter('section_storage') instanceof SectionStorageBase;
  }

  /**
   * Check if the current request is a form submission form layout builder.
   *
   * @return bool
   *   TRUE if considered layout builder, FALSE otherwise.
   */
  public function isLayoutBuilderFormSubmission() {
    $post_data = $this->requestStack->getCurrentRequest()->request;
    return $post_data->has('op') && $post_data->has('toggle_content_preview');
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
   *
   * @todo Review and remove if possible.
   */
  private function setFormContexts(FormStateInterface $form_state) {
    // Provide context so that data can be retrieved.
    $build_info = $form_state->getBuildInfo();
    if (!empty($build_info['args']) && $build_info['args'][0] instanceof OverridesSectionStorage) {
      $section_storage = $build_info['args'][0];
      if ($section_storage->getContext('entity')) {
        try {
          $this->setContext('layout_builder.entity', $build_info['args'][0]->getContext('entity'));
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
    if (!$triggering_element) {
      return FALSE;
    }
    $action = end($triggering_element['#parents']);
    $values = $form_state->getValues();
    return $action == 'preview' && !array_key_exists($current_subform, $values);
  }

  /**
   * Allows block plugins to react to being permantenly added to an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being saved.
   * @param string $uuid
   *   The uuid of the block.
   */
  public function postSave(EntityInterface $entity, $uuid) {}

  /**
   * Allows block plugins to react to being permantenly removed from an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being saved.
   * @param string $uuid
   *   The uuid of the block.
   */
  public function postDelete(EntityInterface $entity, $uuid) {}

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
    $form['actions']['#type'] = 'actions';
    $form['actions']['#attributes']['class'][] = 'canvas-form__actions';
    $form['actions']['#attributes']['class'] = array_unique($form['actions']['#attributes']['class']);

    $this->setFormState($form_state);
    $is_preview = $form_state->get('preview');
    $active_subform = $form_state->get('current_subform');

    if ($active_subform == self::CONTEXTS_FORM_KEY) {
      // Add the context mapping widget to the subform but don't allow access.
      $form['settings']['context_mapping']['#access'] = FALSE;
      foreach (Element::children($form['settings']['context_mapping']) as $element_key) {
        $element = &$form['settings']['context_mapping'][$element_key];
        if ($element['#type'] != 'select') {
          continue;
        }
        $element['#required'] = FALSE;
        if (empty($element['#default_value']) && !empty($element['#options'])) {
          $element['#default_value'] = array_key_first($element['#options']);
        }
        if (count($element['#options']) <= 1) {
          $element['#access'] = FALSE;
        }
      }
      $form['settings']['container']['context_mapping'] = $form['settings']['context_mapping'];
    }

    $this->setElementValidateOnAjaxElements($form['settings']['container']);

    // Assemble the subform buttons.
    $form['actions']['subforms'] = [
      '#type' => 'container',
      '#weight' => -1,
      '#attributes' => [
        'id' => Html::getId('ghi-layout-builder-subform-buttons'),
        'class' => ['ghi-layout-builder-subform-buttons'],
      ],
    ];

    $forms = $this->getSubforms();
    if (count($forms) > 1) {
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
          '#disabled' => $is_preview || (!$this->canShowSubform($form, $form_state, $form_key) && $form_key != self::CONTEXTS_FORM_KEY),
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

    if ($form_state->getBuildInfo()['callback_object'] instanceof AddBlockForm || $form_state->getBuildInfo()['callback_object'] instanceof ImportBlockForm) {
      // For the add block form, make this a link back to the block browser.
      $form['actions']['cancel']['#url'] = Url::fromRoute('layout_builder.choose_block', $this->routeMatch->getRawParameters()->all(), [
        'query' => array_filter([
          'position' => $this->requestStack->getCurrentRequest()->query->get('position') ?? NULL,
          'block_category' => $this->requestStack->getCurrentRequest()->query->get('block_category') ?? NULL,
        ]),
      ]);
      $form['actions']['cancel']['#attributes']['class'][] = 'use-ajax';
    }

    $form['actions']['#weight'] = 99;

    // Set the element validate callback for all ajax enabled form elements.
    // This is needed so that the current form values will be stored in the
    // form and are therefor available for an immediate update of other
    // elements that might depend on the changed data.
    $this->setElementValidateOnAjaxElements($form['settings']['container']);
    $this->setElementValidateOnAjaxElements($form['actions']['subforms']['preview']);

    // Add our own submit handler, so that we can make sure that the original
    // block forms submit handlers are only called once the main add/update
    // button is used.
    // This allows for a lot of ajax interactions without polluting the layout
    // builder tempstore with meaningless changes.
    $form_state->set('original_submit_handlers', $form['#submit']);
    $form['#submit'] = [
      [$this, 'submitForm'],
    ];
  }

  /**
   * Custom form submit.
   *
   * Only call the forms original submit handlers if this is the main submit
   * button.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#parents'];
    if ($parents == ['actions', 'submit']) {
      $form['#submit'] = $form_state->get('original_submit_handlers');
      // If the submit is issued from a preview, the form values are not there
      // and getTemporarySettings() also returns incomplete data for some
      // reason. To work around that, we store the current settings separately
      // on the form state each time they are retrieved.
      if ($form_state->has('current_settings')) {
        $values = $form_state->getValues();
        $values = $form_state->get('current_settings') + $values;
        $form_state->setValues($values);
      }
      $this->formSubmitter->executeSubmitHandlers($form, $form_state);
    }
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
    if (!empty($values['context_mapping'])) {
      $form_state->get('block')->setContextMapping($values['context_mapping']);
    }
    if (!empty($values['data_object'])) {
      $form_state->get('block')->configuration[self::CONTEXTS_FORM_KEY]['data_object'] = $values['context_mapping'];
    }

    // Put them into our form storage.
    if ($values !== NULL) {
      $form_state->set(['storage', $current_subform], $values);
    }

    $block_instance = $form_state->get('block');
    // Setting the form state is important, so that the block instance knows
    // the current state. Without this the checks in canShowSubform can lead to
    // wrong results.
    $block_instance->setFormState($form_state);
    $subforms = $block_instance->getSubforms();
    $requested_subform = array_key_exists('#next_step', $triggering_element) ? $triggering_element['#next_step'] : end($parents);
    if (array_key_exists($requested_subform, $subforms) && ($block_instance->canShowSubform($element, $form_state, $requested_subform) || $requested_subform == self::CONTEXTS_FORM_KEY)) {
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

    $form_subset = NestedArray::getValue($form, $parents);
    if (empty($form_subset) && !empty($triggering_element['#next_step'])) {
      // Support for buttons inside multistep forms, that can submit to a
      // different subform. In that case, the original element parents will
      // point to a subform that is no longer there. Let's try level higher and
      // see if that gives us a form.
      $form_subset = NestedArray::getValue($form, array_slice($parents, 0, -1));
    }

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . $wrapper, $form_subset));

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

        // Also make sure to persist everything to prevent issues when coming
        // back from preview.
        if (!empty($settings)) {
          $form_state->setTemporaryValue($form_key, $settings[$form_key]);
          $form_state->set($storage_key, $settings[$form_key]);
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

    $form_state->set('current_settings', $settings);
    return $settings;
  }

  /**
   * Get the node for the current page.
   *
   * @return \Drupal\node\NodeInterface
   *   The page node if found.
   */
  public function getPageNode() {
    $node = parent::getPageNode();
    if ($node) {
      return $node;
    }

    // Try to find a node from the page.
    $entity = $this->getPageEntity(NodeInterface::class);
    if ($entity instanceof NodeInterface) {
      return $entity;
    }

    // Try to find another content entity and see if it has a reference to a
    // node. This should support page templates, which are custom entities
    // implementing ContentEntityInterface and which reference either section
    // nodes or subpage nodes via an entity reference.
    $entity = $this->getPageEntity();
    if (!$entity || !$entity->hasField('field_entity_reference')) {
      return NULL;
    }
    $referenced_entity_field = $entity->get('field_entity_reference');
    if ($referenced_entity_field->isEmpty()) {
      return NULL;
    }
    $entities = $referenced_entity_field->referencedEntities();
    $referenced_entity = count($entities) == 1 ? reset($entities) : NULL;
    return $referenced_entity instanceof NodeInterface ? $referenced_entity : NULL;
  }

  /**
   * Retrieve the entity for the current page.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $expected_class
   *   The class type that we are looking for.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The page entity.
   */
  public function getPageEntity($expected_class = ContentEntityInterface::class) {
    $page_arguments = $this->getAllAvailablePageParameters();
    $page_entity = NULL;
    if (!empty($page_arguments['section_storage']) && $page_arguments['section_storage'] instanceof SectionStorageInterface) {
      /** @var \Drupal\layout_builder\SectionStorageInterface $section_storage */
      $section_storage = $page_arguments['section_storage'];
      $section_contexts = array_keys($section_storage->getContexts());
      $entity = in_array('entity', $section_contexts) ? $section_storage->getContextValue('entity') : NULL;
      $page_entity = $entity instanceof $expected_class ? $entity : NULL;
    }

    if ($page_entity) {
      return $page_entity;
    }

    foreach ($page_arguments as $page_argument) {
      if (!$page_argument instanceof $expected_class) {
        continue;
      }
      return $page_argument;
    }
    return NULL;
  }

  /**
   * Get the current section node.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface|null
   *   The current section node.
   */
  public function getCurrentSectionNode() {
    $page_node = $this->getPageNode();
    if ($page_node instanceof SectionNodeInterface) {
      return $page_node;
    }
    if ($page_node instanceof SubpageNodeInterface) {
      return $page_node->getParentBaseNode();
    }
    return NULL;
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
    elseif (BaseObjectHelper::getBaseObjectFieldName($page_node)) {
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
  public function getCurrentBaseObject($entity = NULL) {
    $selected_base_object = $this->getSelectedDataObject();
    if ($selected_base_object) {
      return $selected_base_object;
    }
    $page_entity = $entity instanceof ContentEntityInterface ? $entity : $this->getPageEntity();
    if ($page_entity instanceof BaseObjectAwareEntityInterface) {
      return $page_entity->getBaseObject();
    }
    $base_page = $this->getCurrentBaseEntity($page_entity);
    if ($base_page instanceof BaseObjectAwareEntityInterface) {
      return $base_page->getBaseObject();
    }
    $base_objects = $this->getBaseObjectsFromContext();
    return reset($base_objects) ?: NULL;
  }

  /**
   * Get a plan id for the current page context.
   *
   * @return int|null
   *   A plan id if it can be found.
   */
  public function getCurrentBaseObjectId() {
    $base_object = $this->getCurrentBaseObject();
    return $base_object?->getSourceId() ?? NULL;
  }

  /**
   * Get a plan id for the current page context.
   *
   * @return \Drupal\ghi_plans\Entity\Plan|null
   *   A plan object if it can be found.
   */
  public function getCurrentPlanObject() {
    $base_object = $this->getCurrentBaseObject();
    if ($base_object instanceof Plan) {
      return $base_object;
    }
    if ($base_object instanceof BaseObjectChildInterface && $base_object->getParentBaseObject() instanceof Plan) {
      return $base_object->getParentBaseObject();
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
    return $plan_object->getSourceId();
  }

  /**
   * Get the base objects from the current context.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface[]
   *   An array of base objects.
   */
  public function getBaseObjectsFromContext() {
    $contexts = $this->getContexts();
    $base_objects = [];
    foreach ($contexts as $context) {
      if (!$context->hasContextValue()) {
        continue;
      }
      if (!$context->getContextValue() instanceof BaseObjectInterface) {
        continue;
      }
      $base_object = $context->getContextValue();
      $base_objects[$base_object->id()] = $base_object;
    }
    return $base_objects;
  }

  /**
   * Get the selected data object if any.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface|null
   *   A base object to be used as a data object or NULL.
   */
  public function getSelectedDataObject() {
    $selected_data_object_id = $this->getSelectedDataObjectId();
    if (!$selected_data_object_id) {
      return NULL;
    }
    $base_objects = $this->getBaseObjectsFromContext();
    return $base_objects[$selected_data_object_id] ?? NULL;
  }

  /**
   * Get the id of the selected data object if any.
   *
   * @return int|null
   *   A base object id to be used for the data object or NULL.
   */
  public function getSelectedDataObjectId() {
    if ($this->formState && $this->formState->get('data_object')) {
      return $this->formState->get('data_object');
    }
    return $this->configuration[self::CONTEXTS_FORM_KEY]['data_object'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextValue($name) {
    $contexts = $this->getContexts();
    return array_key_exists($name, $contexts) ? $contexts[$name]->getContextValue() : NULL;
  }

  /**
   * Update the context mapping based on the given base object.
   *
   * @param array $context_mapping
   *   An context mapping array.
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object to use.
   */
  protected function updateContextMapping(array &$context_mapping, BaseObjectInterface $base_object) {
    if (!$base_object) {
      return;
    }
    foreach ($context_mapping as $key => $uuid) {
      $context_value = $this->getContextValue($key);
      if (!$context_value) {
        continue;
      }
      // We assume that we have at most one base object per bundle as contexts.
      if ($context_value->bundle() != $base_object->bundle()) {
        continue;
      }
      if ($context_value->id() != $base_object->id()) {
        $context_mapping[$key] = $base_object->getUniqueIdentifier();
        if ($base_object instanceof BaseObjectChildInterface && $parent_object = $base_object->getParentBaseObject()) {
          $this->updateContextMapping($context_mapping, $parent_object);
        }
      }
    }
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
   * {@inheritdoc}
   */
  public function getContexts() {
    $contexts = parent::getContexts();
    $this->moduleHandler->alter('layout_builder_view_context', $contexts);

    // I'm pretty sure that this is not how it should work. But the context
    // mapping is sometimes empty, so in order to use we try to set it up on
    // our own, based on the context definitions.
    $context_mapping = $this->getContextMapping();
    if (empty($context_mapping)) {
      $context_mapping = array_map(function ($definition) use ($contexts) {
        /** @var \Drupal\Core\Plugin\Context\ContextDefinitionInterface $definition */
        foreach ($contexts as $context_key => $context) {
          if ($context->hasContextValue() && $definition->isSatisfiedBy($context)) {
            return $context_key;
          }
        }
        return NULL;
      }, $this->getContextDefinitions());
      $this->setContextMapping($context_mapping);
    }

    foreach (array_filter($context_mapping) as $key => $context_name) {
      $definition = $this->getContextDefinition($key);
      if (substr($definition->getDataType(), 7) != 'base_object') {
        continue;
      }
      if (!strpos($context_name, '--')) {
        continue;
      }
      if (!array_key_exists($context_name, $contexts)) {
        continue;
      }
      $this->setContext($key, $contexts[$context_name]);
      $contexts[$key] = $contexts[$context_name];
    }
    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getDownloadSource() {
    return $this instanceof HPCDownloadPluginInterface ? new BlockSource($this) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableDownloadTypes() {
    $download_types = [];
    // PNG downloads.
    if ($this->moduleHandler->moduleExists('ocha_snap')) {
      if ($this instanceof HPCDownloadPNGInterface) {
        $download_types += [
          HPCDownloadPluginInterface::DOWNLOAD_TYPE_PNG => $this->t('Download PNG'),
        ];
      }
      // PDF downloads.
      if ($this instanceof HPCDownloadPDFInterface) {
        $download_types += [
          HPCDownloadPluginInterface::DOWNLOAD_TYPE_PDF => $this->t('Download PDF'),
        ];
      }
    }
    // Excel downloads.
    if ($this instanceof HPCDownloadExcelInterface) {
      $download_types += [
        HPCDownloadPluginInterface::DOWNLOAD_TYPE_XLSX => $this->t('Download XLSX'),
      ];
    }
    return $download_types;
  }

  /**
   * {@inheritdoc}
   */
  public function getDownloadCaption() {
    $page_title = $this->getPageTitle();
    if ($page_title) {
      return $page_title;
    }

    if ($this->getPageVariant()) {
      // We only use page manager on the homepage, so it's safe to assume this
      // is the homepage. Use the main site title.
      $page_title = $this->t('@site_name @year', [
        '@site_name' => $this->configFactory->get('system.site')->get('name'),
        '@year' => $this->getContextValue('year') ?? '',
      ]);
      return $page_title;
    }

    // Fallback if the page title can't be retrieved at this point.
    $entity = $this->getCurrentBaseEntity();
    $page_arguments = $this->getPageArguments();
    $year = !empty($page_arguments['year']) ? $page_arguments['year'] : NULL;

    // Get the title of an entity.
    $entity_title = $entity ? $entity->label() : NULL;
    // And add the year if necessary. The logic here is to add the year if it
    // doesn't yet appear in the entity title itself.
    $year = $year && (!$entity_title || strpos($entity_title, $year) === FALSE) ? $year : NULL;

    $page_title = implode(' ', array_filter([
      $entity_title,
      $year,
    ]));
    return $page_title;
  }

  /**
   * {@inheritdoc}
   */
  public function buildMetaData() {
    $meta_data = [];
    $meta_data[] = [
      $this->t('Page'),
      $this->getDownloadCaption(),
    ];
    $meta_data[] = [
      $this->t('Export of'),
      $this->label(),
    ];
    $meta_data[] = [
      $this->t('Date'),
      date('d/m/Y H:i'),
    ];
    $meta_data[] = [
      $this->t('Source'),
      Url::fromUserInput($this->getCurrentUri(), [
        'absolute' => TRUE,
        'query' => !empty($options['query']) ? $options['query'] : [],
      ]),
    ];
    return $meta_data;
  }

  /**
   * Get the available admin icons for the block.
   *
   * @return array
   *   An array of render arrays for the icons.
   */
  public function getAdminIcons() {
    $icons = [];
    $endpoint_urls = $this->getFullEndpointUrls();
    if (!empty($endpoint_urls)) {
      $icons['api_url'] = [
        '#theme' => 'hpc_tooltip',
        '#tooltip' => implode('<br />', $endpoint_urls),
        '#class' => 'api-url',
        '#tag_content' => [
          '#theme' => 'hpc_icon',
          '#icon' => 'help',
          '#tag' => 'span',
        ],
      ];
    }
    $icons['configuration'] = [
      '#theme' => 'hpc_popover',
      '#title' => $this->t('Block configuration'),
      '#content' => Markup::create('<pre>' . Yaml::encode(ArrayHelper::mapObjectsToString($this->getConfiguration())) . '</pre>'),
      '#material_icon' => 'content_copy',
      '#class' => 'block-configuration',
    ];
    $block_uuid = $this->getUuid();
    // See if we can get a block instance based on the available information.
    // If not then we don't want to add the reload link as it wouldn't function
    // properly anyway. This situation happens when embedding a full node view,
    // e.g. a homepage node, into a different page.
    $block_instance = BlockHelper::getBlockInstance($this->getCurrentUri(), $this->getPluginId(), $block_uuid);
    if (!empty($block_uuid) && $block_instance) {
      $url = Url::fromRoute('ghi_blocks.load_block', [
        'plugin_id' => $this->getPluginId(),
        'block_uuid' => $block_uuid,
      ]);
      $icons['reload'] = [
        '#type' => 'link',
        '#title' => [
          '#theme' => 'hpc_icon',
          '#icon' => 'refresh',
          '#tag' => 'span',
        ],
        '#url' => $url,
        '#options' => [
          'query' => [
            'current_uri' => $this->getCurrentUri(),
          ] + $this->requestStack->getCurrentRequest()->query->all(),
        ],
        '#attributes' => [
          'class' => [
            'use-ajax',
          ],
        ],
      ];
    }
    return $icons;
  }

}
