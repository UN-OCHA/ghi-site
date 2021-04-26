<?php

namespace Drupal\ghi_blocks\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Render\Element;
use Drupal\hpc_common\Plugin\HPCBlockBase;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Base class for GHI blocks.
 *
 * By inheriting from HPCBlockBase, we get most of the necessary data retrieval
 * logic for block panes and also most of the context gathering logic.
 */
abstract class GHIBlockBase extends HPCBlockBase {

  /**
   * {@inheritdoc}
   */
  abstract public function buildContent();

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

    if (!empty($build_content['#title'])) {
      $build['#title'] = $build_content['#title'];
      unset($build_content['#title']);
    }

    if ($plugin_configuration['label_display'] == 'visible' && !array_key_exists('#title', $build)) {
      $build += [
        '#title' => $this->label(),
      ];
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
   * Define subforms for the block configuration.
   *
   * This allows implementing block plugins to define more complex config
   * forms, using AJAX based multi-step forms. The main logic is handled in
   * this base class. All that implementing classes need to do is to return an
   * associative array, where the keys are the "machine name" of the form,
   * used to store values in the block configuration array, and the value is
   * the name of a callable method on the class that provides the form array
   * for each step.
   */
  public function getSubforms() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFormValueFromFormState(FormStateInterface $form_state, $key) {
    // Extract the form values.
    // https://www.drupal.org/project/drupal/issues/2798261#comment-12735075
    if ($form_state instanceof SubformStateInterface) {
      $values = $form_state->getCompleteFormState()->getValues();
    }
    else {
      $values = $form_state->getValues();
    }

    $form_key = $form_state->get('current_subform');
    if ($step_values = NestedArray::getValue($values, [$form_key, $key])) {
      return $step_values;
    }

    $block = $form_state->get('block');
    $settings_key = ['hpc', $form_key, $key];
    return NestedArray::getValue($block->configuration, $settings_key);
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    parent::blockForm($form, $form_state);

    if (!method_exists($this, 'getSubforms')) {
      return $form;
    }

    // Provide context so that data can be retrieved.
    $build_info = $form_state->getBuildInfo();
    if (!empty($build_info['args']) && $build_info['args'][0] instanceof OverridesSectionStorage) {
      $section_storage = $build_info['args'][0];
      if ($section_storage->getContext('entity')) {
        $this->setContextValue('node', $build_info['args'][0]->getContextValue('entity'));
      }
    }

    $forms = $this->getSubforms();
    if (empty($forms)) {
      return $form;
    }

    $step = $form_state->has('step') ? $form_state->get('step') : 0;
    if ($step > count($forms)) {
      $step = count($forms);
    }

    $form_keys = array_keys($forms);
    $form_key = $form_keys[$step];

    $form_callback = array_values($forms)[$step];

    if (!method_exists($this, $form_callback)) {
      return $form;
    }

    // Set state.
    $form_state->set('step', $step);
    $form_state->set('current_subform', $form_key);
    $form_state->set('block', $this);

    $form['#parents'] = [];

    $wrapper_id = Html::getId('form-wrapper-multi-step-config');

    // Prepare the subform.
    $form['container'] = [
      '#type' => 'container',
      '#parents' => array_merge($form['#parents'], [$form_key]),
      // Provide an anchor for AJAX, so that we know what to replace.
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

    // Set the element validate callback for all ajax enabled form elements.
    // This is needed so that the current form values will be stored in the
    // form and are therefor available for an immediate update of other
    // elements that might depend on the changed data.
    $this->setElementValidateOnAjaxElements($form['container']);

    $form['container']['actions'] = [
      '#type' => 'container',
    ];

    // Add the step navigation.
    if ($step > 0) {
      $form['container']['actions']['back'] = [
        '#type' => 'button',
        '#name' => 'back-button',
        '#button_type' => 'primary',
        '#value' => $this->t('Back'),
        // '#submit' => [['::submitAjax']],
        '#element_validate' => [[$this, 'validateBlockForm']],
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
        // '#submit' => [['::submitAjax']],
        '#element_validate' => [[$this, 'validateBlockForm']],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [$this, 'navigateFormStep'],
          'wrapper' => $wrapper_id,
          'effect' => 'fade',
          'method' => 'replace',
        ],
      ];
    }

    // Add a preview area.
    $preview_wrapper_id = Html::getId($wrapper_id . '-preview');
    $form['container']['actions']['preview'] = [
      '#type' => 'button',
      '#name' => 'next-button',
      '#button_type' => 'primary',
      '#value' => $this->t('Update preview'),
      // '#submit' => [['::submitAjax']],
      '#element_validate' => [[$this, 'validateBlockForm']],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => [$this, 'updatePreview'],
        'wrapper' => $preview_wrapper_id,
        'effect' => 'fade',
        'method' => 'replace',
      ],
    ];

    $form['preview_container'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Preview'),
      '#attributes' => [
        'id' => $preview_wrapper_id,
        'class' => [Html::getClass('hpc-form-wrapper-preview')],
      ],
    ];

    $this->setConfigurationValue('hpc', $this->getTemporarySettings($form_state));
    $form['preview_container']['preview'] = $this->build();

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

    if (array_key_exists('title', $plugin_definition) && $plugin_definition['title'] === FALSE) {
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
    if (!empty($element['#ajax']) && !array_key_exists($element['#element_validate'])) {
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

    if (end($triggering_element['#parents']) == 'submit') {
      // This is the general submit action of the block form.
      // First get the values and the subform key we have been on when the save
      // button was clicked.
      $values = $form_state->getValues();

      $form_key = $form_state->get('current_subform');

      // Massage the value parents so that we can extract the submitted values
      // relating to our subform.
      $value_parents = array_slice($triggering_element['#parents'], 0, -2);
      $value_parents = array_merge($value_parents, [
        $form_key,
      ]);

      // Get the values for that subform and put it into the form storage, so
      // that we have them available in submitBlockForm().
      $step_values = NestedArray::getValue($values, $value_parents);
      unset($step_values['actions']);
      $form_state->set($form_key, $step_values);
      return;
    }

    // Now handle the actual next/back buttons.
    if (end($triggering_element['#parents']) != end($element['#parents'])) {
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
    $array_parents = array_slice($array_parents, 0, array_search('container', $array_parents) + 1);

    // Handle the submitted values and put them into the form storage.
    $values = $form_state->getValues();
    $value_parents = $triggering_element['#parents'];
    $value_parents = array_slice($value_parents, 0, array_search('container', $value_parents) + 1);

    $step_values = NestedArray::getValue($values, $value_parents);
    unset($step_values['actions']);

    $form_key = $form_state->get('current_subform');
    $form_state->set($form_key, $step_values);

    // Set the new step in the storage.
    if (in_array($action, ['next', 'back'])) {
      $step = $form_state->has('step') ? $form_state->get('step') : 0;
      $step = $action == 'next' ? $step + 1 : $step - 1;
      $form_state->set('step', $step);
    }

    // Still no effect it seems.
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
    array_pop($parents);
    array_pop($parents);
    $parents[] = 'preview_container';
    return NestedArray::getValue($form, $parents);
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // This get's called when the block settings are submitted.
    $this->configuration['hpc'] = $this->getTemporarySettings($form_state);
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
    $subforms = $this->getSubforms();
    if (empty($subforms)) {
      return [];
    }

    if ($form_state instanceof SubformStateInterface) {
      $values = $form_state->getCompleteFormState()->getValues();
    }
    else {
      $values = $form_state->getValues();
    }

    // Put stored subform values into the behavior settings for this plugin.
    $settings = [];
    foreach (array_keys($subforms) as $form_key) {
      $settings[$form_key] = $form_state->has($form_key) ? $form_state->get($form_key) : $values[$form_key];
      if (empty($settings[$form_key]) && !empty($this->configuration['hpc'][$form_key])) {
        $settings[$form_key] = $this->configuration['hpc'][$form_key];
      }
      unset($settings[$form_key]['actions']);
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

    if (empty($field_context_mapping)) {
      parent::injectFieldContexts();
      return;
    }

    $node = $this->getNodeFromContexts();
    if (!$node) {
      return;
    }

    $plan_node = $this->getCurrentPlanNode($node);
    $plan_id = $plan_node->field_original_id->value;

    if (empty($plugin_definition['context_definitions'][$context_key])) {
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
   * @return int
   *   A plan id if it can be found.
   */
  public function getCurrentPlanNode($page_node = NULL) {
    if ($page_node === NULL) {
      $page_node = $this->getPageNode();
    }
    if (!$page_node) {
      return NULL;
    }
    if ($page_node->bundle() == 'plan') {
      return $page_node->field_original_id->value;
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
