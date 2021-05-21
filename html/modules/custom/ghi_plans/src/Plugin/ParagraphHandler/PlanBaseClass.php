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
<<<<<<< HEAD
   * Key used for storage.
   */
  const KEY = '';

  /**
   * Default configuration.
   */
  const DEFAULT_CONFIG = [];

  /**
=======
>>>>>>> develop
   * Get data for this paragraph.
   *
   * @param string $source_key
   *   The source key that should be used to retrieve data for a paragraph.
   *
   * @return array|object
   *   A data array or object.
   */
  protected function getData(string $source_key = 'data') {
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
    if ($this->parentEntity->bundle() == 'plan') {
      if (isset($this->parentEntity->field_original_id) && !$this->parentEntity->field_original_id->isEmpty()) {
        $plan_id = $this->parentEntity->field_original_id->value;
        $query_handler->setPlaceholder('plan_id', $plan_id);
      }
    }
    elseif ($this->parentEntity->hasField('field_plan') && count($this->parentEntity->get('field_plan')->referencedEntities()) == 1) {
      $plan = reset($this->parentEntity->get('field_plan')->referencedEntities());
      $plan_id = $plan->field_original_id->value;
      $query_handler->setPlaceholder('plan_id', $plan_id);
    }
    return $query_handler;
  }

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
  public function widgetAlter(&$element, &$form_state, $context) {
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
  public static function validate(array &$element, FormStateInterface $form_state) {
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

}
