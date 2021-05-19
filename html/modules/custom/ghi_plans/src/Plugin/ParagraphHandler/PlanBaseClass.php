<?php

namespace Drupal\ghi_plans\Plugin\ParagraphHandler;

use Drupal\Component\Utility\NestedArray;
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
  }

  /**
   * {@inheritdoc}
   */
  public static function submit(&$element, FormStateInterface $form_state) {
    // Get field name and delta from parents.
    $parents = $element['#parents'];
    $field_name = array_shift($parents);
    $delta = array_shift($parents);

    // Get paragraph from widget state.
    $widget_state = WidgetBase::getWidgetState([], $field_name, $form_state);

    // Get actual values.
    $values = NestedArray::getValue($form_state->getValues(), $element['#parents']);

    // Set widget state.
    if ($values && is_array($values)) {
      $widget_state['paragraphs'][$delta]['entity']->setBehaviorSettings(static::KEY, $values);
      $widget_state['paragraphs'][$delta]['entity']->setNeedsSave(TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(array &$build) {
  }

  /**
   * Return behavior settings.
   */
  protected function getConfig() {
    $settings = $this->paragraph->getAllBehaviorSettings();
    $config = $settings[static::KEY] ?? [];

    return $config;
  }

}
