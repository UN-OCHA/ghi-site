<?php

namespace Drupal\ghi_blocks\Plugin\Block;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Render\Element;
use Drupal\ghi_blocks\Interfaces\AutomaticTitleBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Traits\AjaxBlockFormTrait;
use Drupal\hpc_common\Plugin\HPCBlockBase;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Base class for GHI blocks.
 *
 * By inheriting from HPCBlockBase, we get most of the necessary data retrieval
 * logic for block panes and also most of the context gathering logic.
 */
abstract class GHIBlockBase extends HPCBlockBase {

  use AjaxBlockFormTrait;

  /**
   * Current form state object if in a configuration context.
   *
   * @var \Drupal\Core\Form\FormStateInterface
   */
  protected $formState;

  /**
   * {@inheritdoc}
   */
  abstract public function buildContent();

  /**
   * Provide block specific default configuration.
   */
  abstract protected function getConfigurationDefaults();

  /**
   * Get data for this paragraph.
   *
   * @param string $source_key
   *   The source key that should be used to retrieve data for a paragraph.
   *
   * @return array|object
   *   A data array or object.
   */
  public function getData(string $source_key = 'data') {
    $query = $this->getQueryHandler($source_key);
    return $query ? $query->getData() : NULL;
  }

  /**
   * Get a query handler for this paragraph.
   *
   * This returns either the requested named handler if it exists, or the only
   * one defined if no source key is given.
   *
   * @param string $source_key
   *   The source key that should be used to retrieve data for a paragraph.
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
    if ($page_node->bundle() == 'plan') {
      if (isset($page_node->field_original_id) && !$page_node->field_original_id->isEmpty()) {
        $plan_id = $page_node->field_original_id->value;
        $query_handler->setPlaceholder('plan_id', $plan_id);
      }
    }
    elseif ($page_node->hasField('field_plan') && count($page_node->get('field_plan')->referencedEntities()) == 1) {
      $entities = $page_node->get('field_plan')->referencedEntities();
      $plan = reset($entities);
      $plan_id = $plan->field_original_id->value;
      $query_handler->setPlaceholder('plan_id', $plan_id);
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

    if ($this->shouldDisplayTitle()) {
      if ($this instanceof AutomaticTitleBlockInterface) {
        $build['#title'] = $this->getAutomaticBlockTitle();
      }
      elseif (!empty($build_content['#title'])) {
        $build['#title'] = $build_content['#title'];
        unset($build_content['#title']);
      }

      if ($plugin_configuration['label_display'] == 'visible' && !array_key_exists('#title', $build)) {
        $build += [
          '#title' => $this->label(),
        ];
      }
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
    $form_key = $form_state->get('current_subform');
    $step_values = NULL;
    $value_parents = [$form_key, $key];
    if ($form_state instanceof SubformStateInterface) {
      $step_values = $form_state->getCompleteFormState()->cleanValues()->getValue($value_parents);
    }
    else {
      $step_values = $form_state->cleanValues()->getValue($value_parents);
    }
    if ($step_values) {
      return $step_values;
    }

    $block = $form_state->get('block');
    $config = $block->getBlockConfig();

    $settings_key = [$key];
    if ($block instanceof MultiStepFormBlockInterface) {
      $settings_key = [$form_key, $key];
    }
    return NestedArray::getValue($config, $settings_key);
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    parent::blockForm($form, $form_state);

    $this->formState = $form_state;
    $form_state->addCleanValueKey('actions');

    // Provide context so that data can be retrieved.
    $build_info = $form_state->getBuildInfo();
    if (!empty($build_info['args']) && $build_info['args'][0] instanceof OverridesSectionStorage) {
      $section_storage = $build_info['args'][0];
      if ($section_storage->getContext('entity')) {
        $this->setContextValue('node', $build_info['args'][0]->getContextValue('entity'));
      }
    }

    // Default is a simple form with a single configuration callback.
    $has_step_navigation = FALSE;
    $form_key = 'basic';
    $form_callback = 'getConfigForm';
    $step = NULL;

    if ($this instanceof MultiStepFormBlockInterface) {
      $forms = $this->getSubforms();
      if (empty($forms)) {
        return $form;
      }

      $step = $form_state->has('step') ? $form_state->get('step') : 0;
      if ($step > count($forms)) {
        $step = count($forms);
      }
      $has_step_navigation = count($forms) > 1;

      $form_keys = array_keys($forms);
      $form_key = $form_keys[$step];

      $form_callback = array_values($forms)[$step];

      if (!method_exists($this, $form_callback)) {
        return $form;
      }

      // Set state.
      $form_state->set('step', $step);
    }

    $form_state->set('current_subform', $form_key);
    $form_state->set('block', $this);

    $form['#parents'] = [];
    $form['#array_parents'] = [];

    $wrapper_id = Html::getId('form-wrapper-ghi-block-config');

    // Prepare the subform.
    $form['container'] = [
      '#type' => 'container',
      // This is important for form processing and value submission.
      '#parents' => array_merge($form['#parents'], [$form_key]),
      // Provide an anchor for AJAX, so that we know what to replace. See
      // Drupal\block\BlockForm::form for where that comes from.
      '#array_parents' => array_merge($form['#array_parents'], [
        'settings',
        'container',
      ]),
      '#attributes' => [
        'id' => $wrapper_id,
        'class' => [Html::getClass('hpc-form-wrapper')],
      ],
      '#attached' => [
        'library' => ['ghi_blocks/layout_builder_modal_admin'],
      ],
    ];

    // Set initial values for this form step.
    if ($form_state->has($form_key)) {
      if ($form_state instanceof SubformStateInterface) {
        $form_state->getCompleteFormState()->setValue($form['container']['#parents'], $form_state->get($form_key));
      }
    }

    // And build the subform structure.
    $subform_state = SubformState::createForSubform($form['container'], $form, $form_state);
    $form['container'] += $this->{$form_callback}($form['container'], $subform_state);

    $form['container']['actions'] = [
      '#type' => 'container',
    ];

    // Add the step navigation.
    if ($has_step_navigation) {
      if ($step > 0) {
        $form['container']['actions']['back'] = [
          '#type' => 'button',
          '#name' => 'back-button',
          '#button_type' => 'primary',
          '#value' => $this->t('Back'),
          '#limit_validation_errors' => [],
          '#ajax' => [
            'callback' => [$this, 'navigateFormStep'],
            'wrapper' => $wrapper_id,
            'effect' => 'fade',
            'method' => 'replace',
          ],
        ];
      }

      if ($step < count($forms) - 1) {
        $form['container']['actions']['next'] = [
          '#type' => 'button',
          '#name' => 'next-button',
          '#button_type' => 'primary',
          '#value' => $this->t('Next'),
          '#limit_validation_errors' => [],
          '#ajax' => [
            'callback' => [$this, 'navigateFormStep'],
            'wrapper' => $wrapper_id,
            'effect' => 'fade',
            'method' => 'replace',
          ],
        ];
      }
    }

    // Add a preview area.
    $preview_wrapper_id = Html::getId($wrapper_id . '-preview');

    $form['preview_container'] = [
      '#type' => 'container',
      '#title' => $this->t('Preview'),
      '#attributes' => [
        'id' => $preview_wrapper_id,
        'class' => [Html::getClass('hpc-form-wrapper-preview')],
      ],
    ];
    $form['preview_container']['update_preview'] = [
      '#type' => 'button',
      '#name' => 'preview-button',
      '#button_type' => 'primary',
      '#value' => $this->t('Update preview'),
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => [$this, 'updatePreview'],
        'wrapper' => $preview_wrapper_id,
        'effect' => 'fade',
        'method' => 'replace',
      ],
      '#attributes' => [
        'class' => !array_key_exists('#preview_button_hidden', $form['container']) || $form['container']['#preview_button_hidden'] ? ['visually-hidden'] : [],
      ],
    ];

    $build = $this->build();
    $form['preview_container']['preview'] = [
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

    // Set the element validate callback for all ajax enabled form elements.
    // This is needed so that the current form values will be stored in the
    // form and are therefor available for an immediate update of other
    // elements that might depend on the changed data.
    $this->setElementValidateOnAjaxElements($form['container']);

    $form['#after_build'][] = [$this, 'blockFormAfterBuild'];
    return $form;
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

    $form['admin_label']['#access'] = FALSE;
    $form['admin_label']['#value'] = (string) $plugin_definition['admin_label'];

    if ($this instanceof AutomaticTitleBlockInterface) {
      $form['label']['#access'] = FALSE;
      $form['label']['#required'] = FALSE;
      $form['label_display']['#access'] = FALSE;
      $form['label_display']['#value'] = TRUE;
    }

    if (!$this->shouldDisplayTitle()) {
      $form['label']['#access'] = FALSE;
      $form['label']['#value'] = (string) $plugin_definition['admin_label'];
      $form['label_display']['#access'] = FALSE;
      $form['label_display']['#value'] = FALSE;
    }
    return $form;
  }

  /**
   * Recursively set the element validate property on ajax form elements.
   *
   * @param array $element
   *   The form element array.
   */
  private function setElementValidateOnAjaxElements(array &$element) {
    if (!empty($element['#ajax']) && !array_key_exists('#element_validate', $element)) {
      $element['#element_validate'] = [[$this, 'validateBlockForm']];
    }
    foreach (Element::children($element) as $element_key) {
      $this->setElementValidateOnAjaxElements($element[$element_key]);
    }
  }

  /**
   * Validate the current page in the settings form.
   *
   * This should only be used for validation, but we use it to set the current
   * navigation step too. Probably not the best idea.
   *
   * @param array $element
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state interface.
   */
  public function validateBlockForm(array &$element, FormStateInterface $form_state) {

    // Every button get's validated, but we only need to handle this once.
    $triggering_element = $form_state->getTriggeringElement();

    // Get the subform key we have been on when the save button was clicked.
    $form_key = $form_state->get('current_subform');

    $action = end($triggering_element['#parents']);
    if (in_array($action, ['submit', 'update_preview'])) {
      // This is the general submit action of the block form.
      // Massage the value parents so that we can extract the submitted values
      // relating to our subform.
      $parents = array_slice($triggering_element['#parents'], 0, array_search('preview_container', $triggering_element['#parents']));
      $value_parents = array_slice($parents, 0, array_search($form_key, $parents) + 1);
      if (empty($value_parents)) {
        $value_parents[] = $form_key;
      }
      // Get the values for that subform and .
      $step_values = $form_state->cleanValues()->getValue($value_parents);
      if ($action == 'submit') {
        // For the final submit of the form, put the values into the form
        // storage fo the current form, so that we have them available later.
        $form_state->set($form_key, $step_values);
      }
      else {
        // For the preview, set the current step values.
        $form_state->setValue($form_key, $step_values);
        $form_state->setTemporaryValue($form_key, $step_values);
        // Important to rebuild, otherwhise the preview won't update.
        $form_state->setRebuild();
      }
      return;
    }

    // Now handle the actual next/back buttons.
    if ($action != end($element['#parents'])) {
      return;
    }

    // Make sure this is about the container part of the form.
    $array_parents = $triggering_element['#array_parents'];
    if (!in_array('container', $array_parents)) {
      // If there is no container up the chain, we are not in the right place.
      return;
    }

    // Get the action, this is only important for the navigation between the
    // form steps.
    $action = array_pop($array_parents);

    // Handle the submitted values and put them into the form storage.
    $value_parents = $triggering_element['#parents'];
    $value_parents = array_slice($value_parents, 0, array_search($form_key, $value_parents) + 1);

    $step_values = $form_state->cleanValues()->getValue($value_parents);
    $form_state->set($form_key, $step_values);

    // Set the new step in the storage.
    if (in_array($action, ['next', 'back'])) {
      $step = $form_state->has('step') ? $form_state->get('step') : 0;
      $step = $action == 'next' ? $step + 1 : $step - 1;
      $form_state->set('step', $step);
    }

    // And rebuild.
    $form_state->setRebuild();
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
    $parents = $triggering_element['#array_parents'];
    array_pop($parents);
    array_pop($parents);
    return NestedArray::getValue($form, $parents);
  }

  /**
   * Ajax callback to update the preview.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state interface.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Ajax response.
   */
  public function updatePreview(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#array_parents'];
    array_pop($parents);
    $this->messenger()->deleteAll();
    return NestedArray::getValue($form, $parents);
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

    // Set the HPC specific block config.
    $this->setBlockConfig($this->getTemporarySettings($form_state));

    if ($this instanceof AutomaticTitleBlockInterface) {
      // This is important to set, otherwise template_preprocess_block() will
      // hide the block title.
      $this->configuration['label_display'] = TRUE;
    }
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

    if ($this instanceof MultiStepFormBlockInterface) {
      $subforms = $this->getSubforms();
      if (empty($subforms)) {
        return [];
      }
      // Put stored subform values into the behavior settings for this plugin.
      $settings = [];
      foreach (array_keys($subforms) as $form_key) {
        $settings[$form_key] = $form_state->has($form_key) ? $form_state->get($form_key) : $values[$form_key];
        if (empty($settings[$form_key]) && !empty($this->configuration['hpc'][$form_key])) {
          $settings[$form_key] = $this->configuration['hpc'][$form_key];
        }
      }
    }
    else {
      $form_key = 'basic';
      // Put stored subform values into the behavior settings for this plugin.
      // There are multiple places where these can be stored, so we look at
      // each of them in order.
      $temporary_values = $form_state->hasTemporaryValue($form_key) ? $form_state->getTemporaryValue($form_key) : NULL;
      $storage_values = $form_state->has($form_key) ? $form_state->get($form_key) : NULL;
      $submitted_values = !empty($values[$form_key]) ? $values[$form_key] : NULL;
      $settings = !empty($temporary_values) ? $temporary_values : (!empty($storage_values) ? $storage_values : $submitted_values);

      // If we still have nothing, we fall back to the existing configuration.
      if (empty($settings)) {
        $settings = $this->configuration['hpc'];
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

    $plan_node = $this->getCurrentPlanNode($node);
    $plan_id = $plan_node->field_original_id->value;

    if (empty($plugin_definition['context_definitions']['plan_id'])) {
      // Create a new context.
      $context = new Context(new ContextDefinition('integer', $this->t('Plan id'), FALSE), $plan_id);
      $this->setContext('plan_id', $context);
    }
    else {
      // Overwrite the existing context value if there is any.
      $this->setContextValue('plan_id', $plan_id);
    }
    $this->injectedFieldContexts = TRUE;
  }

  /**
   * Get a plan id for the current page context.
   *
   * @return \Drupal\node\NodeInterface
   *   A plan node if it can be found.
   */
  public function getCurrentPlanNode($page_node = NULL) {
    if ($page_node === NULL) {
      $page_node = $this->getPageNode();
    }
    if (!$page_node) {
      return NULL;
    }

    if ($page_node->bundle() == 'plan') {
      return $page_node;
    }
    if ($page_node->hasField('field_plan') && $referenced_entities = $page_node->field_plan->referencedEntities()) {
      return count($referenced_entities) ? reset($referenced_entities) : NULL;
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
    $plan_node = $this->getCurrentPlanNode($page_node);
    if (!$plan_node) {
      return NULL;
    }
    return $plan_node->field_original_id->value;
  }

}
