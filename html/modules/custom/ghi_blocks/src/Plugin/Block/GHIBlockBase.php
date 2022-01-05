<?php

namespace Drupal\ghi_blocks\Plugin\Block;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactory;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\Router;
use Drupal\ghi_blocks\Interfaces\AutomaticTitleBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\LayoutBuilder\SelectionCriteriaArgument;
use Drupal\ghi_form_elements\ConfigurationContainerItemManager;
use Drupal\ghi_sections\SectionManager;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_common\Plugin\HPCBlockBase;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for GHI blocks.
 *
 * By inheriting from HPCBlockBase, we get most of the necessary data retrieval
 * logic for block panes and also most of the context gathering logic.
 */
abstract class GHIBlockBase extends HPCBlockBase {

  const DEFAULT_FORM_KEY = 'basic';

  /**
   * Current form state object if in a configuration context.
   *
   * @var \Drupal\Core\Form\FormStateInterface
   */
  protected $formState;

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
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $request_stack, Router $router, KeyValueFactory $keyValueFactory, EndpointQuery $endpoint_query, EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system, ConfigurationContainerItemManager $configuration_container_item_manager, SectionManager $section_manager, SelectionCriteriaArgument $selection_criteria_argument) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $request_stack, $router, $keyValueFactory, $endpoint_query, $entity_type_manager, $file_system);
    $this->configurationContainerItemManager = $configuration_container_item_manager;
    $this->sectionManager = $section_manager;
    $this->selectionCriteriaArgument = $selection_criteria_argument;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('router.no_access_checks'),
      $container->get('keyvalue'),
      $container->get('hpc_api.endpoint_query'),
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('plugin.manager.configuration_container_item_manager'),
      $container->get('ghi_sections.manager'),
      $container->get('ghi_blocks.layout_builder_edit_page.selection_criteria_argument')
    );
  }

  /**
   * {@inheritdoc}
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
   *
   * @param string $source_key
   *   The source key that should be used to retrieve data for a block.
   *
   * @return Drupal\hpc_api\EndpointQuery
   *   The query handler class.
   */
  protected function getQueryHandler($source_key = 'data') {
    $configuration = $this->getPluginDefinition();
    if (empty($configuration['data_sources'])) {
      return NULL;
    }

    $sources = $configuration['data_sources'];
    $definition = !empty($sources[$source_key]) ? $sources[$source_key] : NULL;
    if (!$definition || empty($definition['service'])) {
      return NULL;
    }

    $query_handler = \Drupal::service($definition['service']);
    $page_node = $this->getPageNode();

    $base_entity = NULL;

    // Get the section for the current page node.
    if ($page_node) {
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
    }

    // Get the object for the current page node.
    $base_object = NULL;
    if ($base_entity && $base_entity->hasField('field_base_object') && count($base_entity->get('field_base_object')->referencedEntities()) == 1) {
      // The page node is a section node which references a base object.
      $entities = $base_entity->get('field_base_object')->referencedEntities();
      $base_object = reset($entities);
    }

    if ($base_object) {
      $original_id = $base_object->field_original_id->value;
      $query_handler->setPlaceholder($base_object->bundle() . '_id', $original_id);
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

    // Get the build content from the block plugin.
    $build_content = $this->buildContent();
    if (!$build_content) {
      return [];
    }

    $build = [
      '#type' => 'container',
    ];

    // Handle the title display.
    if ($this->shouldDisplayTitle()) {
      if ($this instanceof AutomaticTitleBlockInterface) {
        $build['#title'] = $this->getAutomaticBlockTitle();
      }
      elseif (!empty($build_content['#title'])) {
        $build['#title'] = $build_content['#title'];
        unset($build_content['#title']);
      }

      if (empty($plugin_configuration['label']) && $this->hasDefaultTitle()) {
        $plugin_definition = $this->getPluginDefinition();
        $build['#title'] = $plugin_definition['default_title'];
      }

      if ($plugin_configuration['label_display'] == 'visible' && !array_key_exists('#title', $build)) {
        $build += [
          '#title' => $this->label(),
        ];
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
    $build['#attributes']['class'][] = 'ghi-block-' . $this->getUuid();

    $build['#title_attributes']['class'][] = 'block-title';
    if (empty($build['#region'])) {
      $build['#region'] = $this->getRegion();
    }

    return $build;
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

    $step_values = NULL;
    $value_parents = [$current_subform, $key];

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

    $settings_key = [$key];
    if ($block->isMultistepForm()) {
      $settings_key = [$current_subform, $key];
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

    // Provide context so that data can be retrieved.
    $build_info = $form_state->getBuildInfo();
    if (!empty($build_info['args']) && $build_info['args'][0] instanceof OverridesSectionStorage) {
      $section_storage = $build_info['args'][0];
      if ($section_storage->getContext('entity')) {
        try {
          $this->setContextValue('node', $build_info['args'][0]->getContextValue('entity'));
        }
        catch (ContextException $e) {
          // Fail silently.
        }
      }
    }

    // Default is a simple form with a single configuration callback.
    $current_subform = self::DEFAULT_FORM_KEY;
    $form_callback = 'getConfigForm';
    $is_base_form = TRUE;

    if ($this->isMultistepForm()) {
      $forms = $this->getSubforms();
      if (empty($forms)) {
        return $form;
      }

      $current_subform = $form_state->get('current_subform');
      if (!$current_subform || !array_key_exists($current_subform, $forms)) {
        $current_subform = $this->getDefaultSubform();
      }
      $subform = $forms[$current_subform];
      $form_callback = $subform['callback'];
      $is_base_form = !empty($subform['base_form']);

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
      '#attached' => [
        'library' => ['ghi_blocks/layout_builder_modal_admin'],
      ],
    ];

    if (!$form_state->get('preview')) {
      if ($is_base_form) {
        // Add the label widget to the base form.
        $form['container']['label'] = $form['label'];
        $form['container']['label_display'] = $form['label_display'];

        // Set the default values.
        $plugin_definition = $this->getPluginDefinition();
        if ($this->hasDefaultTitle() && (string) $form['container']['label']['#default_value'] == (string) $plugin_definition['admin_label']) {
          $form['container']['label']['#default_value'] = '';
        }
      }

      // And build the subform structure.
      $subform_state = SubformState::createForSubform($form['container'], $form, $form_state);
      $form['container'] += $this->{$form_callback}($form['container'], $subform_state);
      $this->addButtonsToCleanValueKeys($form['container'], $form_state, $form['container']['#parents']);

      // Add after build callback for label handling.
      $form['#after_build'][] = [$this, 'blockFormAfterBuild'];
    }
    else {
      // Show a preview area.
      $build = $this->build();
      $form['container']['preview'] = [
        '#theme' => 'block',
        '#attributes' => [],
        '#configuration' => [
          'label' => array_key_exists('#title', $build) ? $build['#title'] : NULL,
          'label_display' => $this->configuration['label_display'],
          'hpc' => $this->getTemporarySettings($form_state),
        ] + $this->configuration,
        '#base_plugin_id' => $this->getBaseId(),
        '#plugin_id' => $this->getPluginId(),
        '#derivative_plugin_id' => $this->getDerivativeId(),
        '#id' => $this->getPluginId(),
        'content' => $build,
      ];
    }

    return $form;
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
    $plugin_configuration = $this->getConfiguration();

    // Disable all of the default settings elements. We will handle them.
    $form['admin_label']['#access'] = FALSE;
    $form['admin_label']['#value'] = (string) $plugin_definition['admin_label'];
    $form['label']['#access'] = FALSE;
    $form['label']['#required'] = FALSE;
    $form['label']['#value'] = (string) $plugin_configuration['label'];
    $form['label_display']['#access'] = FALSE;
    $form['label_display']['#value'] = (string) $plugin_configuration['label_display'];
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

      if ($this->hasDefaultTitle()) {
        // This block plugin provides a default title, se the label field is
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

    // Values on multi step forms are internally stored keyed by the respective
    // form keys of each available subform of the multi step form.
    $value_parents = [];
    if ($this->isMultistepForm()) {
      $value_parents[] = $this->getBaseSubFormKey();
    }

    // Because we handle the label fields, we also have to update the
    // configuration.
    $this->configuration['label'] = NestedArray::getValue($values, array_merge($value_parents, ['label']));
    $this->configuration['label_display'] = NestedArray::getValue($values, array_merge($value_parents, ['label_display']));

    // Set the HPC specific block config.
    $this->setBlockConfig($values);

    if ($this instanceof AutomaticTitleBlockInterface || $this->hasDefaultTitle()) {
      // This is important to set, otherwise template_preprocess_block() will
      // hide the block title.
      $this->configuration['label_display'] = TRUE;
    }
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
    $form['actions']['subforms'] = [
      '#type' => 'container',
      '#weight' => -1,
      '#attributes' => [
        'id' => Html::getId('ghi-layout-builder-subform-buttons'),
        'class' => ['ghi-layout-builder-subform-buttons'],
      ],
    ];

    $is_preview = $form_state->get('preview');

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
      // '#element_validate' => [get_class($this) . '::previewValidate'],
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

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'layout-builder-cancel',
      "#button_type" => 'primary',
      "#ajax" => [
        "callback" => 'ghi_blocks_layout_builder_cancel_callback',
      ],
    ];
    $form['actions']['#weight'] = 99;

    // Set the element validate callback for all ajax enabled form elements.
    // This is needed so that the current form values will be stored in the
    // form and are therefor available for an immediate update of other
    // elements that might depend on the changed data.
    $this->setElementValidateOnAjaxElements($form['settings']['container']);
    $this->setElementValidateOnAjaxElements($form['actions']['subforms']['preview']);
  }

  /**
   * Get the base form key if this is a multi step form.
   *
   * @return string
   *   Get the subform key for the base form.
   */
  private function getBaseSubFormKey() {
    if (!$this->isMultistepForm()) {
      return self::DEFAULT_FORM_KEY;
    }
    $subforms = $this->getSubforms();
    foreach ($subforms as $subform_key => $subform) {
      if (!empty($subform['base_form'])) {
        return $subform_key;
      }
    }
    // Fallback is the first defined subform.
    return array_key_first($subforms);
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
   * {@inheritdoc}
   */
  public function injectFieldContexts() {
    if ($this->injectedFieldContexts) {
      return;
    }
    $plugin_definition = $this->getPluginDefinition();
    $field_context_mapping = !empty($plugin_definition['field_context_mapping']) ? $plugin_definition['field_context_mapping'] : NULL;

    if (!empty($field_context_mapping)) {
      parent::injectFieldContexts();
      return;
    }

    $node = $this->getNodeFromContexts();
    if (!$node) {
      return;
    }

    $base_object = $this->getCurrentBaseObject($node);
    if (!$base_object || !$base_object->hasField('field_original_id')) {
      return;
    }
    $base_object_id = $base_object->field_original_id->value;
    $base_object_bundle = $base_object->bundle();
    $context_key = $base_object_bundle . '_id';
    $context_label = $this->t('@bundle id', [
      '@bundle' => $base_object->type->entity->label(),
    ]);

    if (empty($plugin_definition['context_definitions'][$context_key])) {
      // Create a new context.
      $context = new Context(new ContextDefinition('integer', $context_label, FALSE), $base_object_id);
      $this->setContext($context_key, $context);
    }
    else {
      // Overwrite the existing context value if there is any.
      $this->setContextValue($context_key, $base_object_id);
    }
    $this->injectedFieldContexts = TRUE;
  }

  /**
   * Get the base object for the current page context.
   *
   * @return \Drupal\node\NodeInterface
   *   A node representing an API base object if it can be found.
   */
  public function getCurrentBaseObject($page_node = NULL) {
    if ($page_node === NULL) {
      $page_node = $this->getPageNode();
    }
    if (!$page_node) {
      return NULL;
    }

    if ($page_node->hasField('field_base_object') && $base_objects = $page_node->field_base_object->referencedEntities()) {
      return count($base_objects) ? reset($base_objects) : NULL;
    }
    if ($page_node->hasField('field_entity_reference') && $referenced_entities = $page_node->field_entity_reference->referencedEntities()) {
      return count($referenced_entities) ? $this->getCurrentBaseObject(reset($referenced_entities)) : NULL;
    }
    return NULL;
  }

  /**
   * Get a plan id for the current page context.
   *
   * @return int
   *   A plan id if it can be found.
   */
  public function getCurrentBaseObjectId($page_node = NULL) {
    $base_object = $this->getCurrentBaseObject($page_node);
    if (!$base_object) {
      return NULL;
    }
    return $base_object->field_original_id->value;
  }

  /**
   * Get a plan id for the current page context.
   *
   * @return \Drupal\node\NodeInterface
   *   A plan node if it can be found.
   */
  public function getCurrentPlanObject($page_node = NULL) {
    if ($page_node === NULL) {
      $page_node = $this->getPageNode();
    }
    if (!$page_node) {
      return NULL;
    }

    $base_object = $this->getCurrentBaseObject($page_node);
    if ($base_object->bundle() == 'plan') {
      return $base_object;
    }
    return NULL;
  }

  /**
   * Get a plan id for the current page context.
   *
   * @return int
   *   A plan id if it can be found.
   */
  public function getCurrentPlanId($page_node = NULL) {
    $plan_object = $this->getCurrentPlanObject($page_node);
    if (!$plan_object) {
      return NULL;
    }
    return $plan_object->field_original_id->value;
  }

  /**
   * Get the specified named argument for the current page.
   *
   * This also checks whether the retrieved argument is a default value, in
   * which case it also checks woth the selectin criteria argument service to
   * see if a page argument can be extracted from the current request. This
   * would be the case when a page manager page, that is using layout builder,
   * is being edited.
   */
  protected function getPageArgument($key) {
    $context_value = parent::getPageArgument($key);

    $context = $this->hasContext($key) ? $this->getContext($key) : NULL;
    if (!$context) {
      return $context_value;
    }

    $definition = $context->getContextDefinition();
    if ($context_value !== NULL && $definition->getDefaultValue() == $context_value) {
      // This is a default value. So let's check if we can get an argument from
      // the selection criteria.
      $value_from_selection_criteria = $this->selectionCriteriaArgument->getArgumentFromSelectionCriteria($key);
      if ($value_from_selection_criteria) {
        return $value_from_selection_criteria;
      }
    }

    return $context_value;
  }

}
