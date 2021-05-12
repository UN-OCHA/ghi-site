<?php

namespace Drupal\ghi_plans\Plugin\ParagraphHandler;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_paragraph_handler\Plugin\ParagraphHandlerBase;

/**
 * Base class for paragraph handlers.
 */
class PlanBaseClass extends ParagraphHandlerBase {

  /**
   * Key used for storage.
   */
  const KEY = '';

  /**
   * {@inheritdoc}
   */
  public function preprocess(array &$variables, array $element) {
    if ($this->isNested()) {
      $variables['nested_class'] = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function widget_alter(&$element, &$form_state, $context) {
    $subform = &$element['subform'];

    // @see https://www.drupal.org/project/drupal/issues/2820359
    $subform['#element_submit'] = [[get_called_class(), 'submit']];
    $subform['#element_validate'] = [[get_called_class(), 'validate']];
  }

  /**
   * Submit handler for the subform.
   *
   * @param array $element
   *   The form element array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   */
  public static function submit(array &$element, FormStateInterface $form_state) {
    // Get field name and delta from parents.
    $parents = $element['#parents'];
    $field_name = array_shift($parents);
    $delta = array_shift($parents);

    // Get paragraph from widget state.
    $widget_state = WidgetBase::getWidgetState([], $field_name, $form_state);

    // Get actual values.
    $values = $form_state->getValue($element['#parents']);

    // Set widget state.
    if ($values && is_array($values)) {
      $widget_state['paragraphs'][$delta]['entity']->setBehaviorSettings(static::KEY, $values);
      $widget_state['paragraphs'][$delta]['entity']->setNeedsSave(TRUE);
    }
  }

  /**
   * Validate handler for the subform.
   *
   * @param array $element
   *   The form element array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   */
  public function validate(array &$element, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#parents'];
    array_pop($parents);
    if ($parents == $element['#parents']) {
      $form_state->set(static::KEY, $form_state->getValue($parents));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(array &$build) {
  }

  /**
   * Return behavior settings.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An optional form state interface if temporary values should be retrieved
   *   from the current configuration form.
   *
   * @return array
   *   A configuration array, specific to the type of paragraph being edited.
   */
  protected function getConfig(FormStateInterface $form_state = NULL) {
    $settings = $this->paragraph->getAllBehaviorSettings();
    $config = $settings[static::KEY] ?? [];

    if ($form_state !== NULL && $form_state->has(static::KEY)) {
      $config = $form_state->get(static::KEY);
    }

    return $config;
  }

}
