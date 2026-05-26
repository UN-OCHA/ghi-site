<?php

namespace Drupal\ghi_plans\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Provides logic for form alters.
 */
class PlanFormAlter {

  /**
   * Form alter callback for the plan base object entity form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function formAlter(&$form, FormStateInterface $form_state): void {
    /** @var \Drupal\ghi_plans\Entity\Plan $plan */
    $plan = $form['#entity'];

    $form['#attached']['library'][] = 'ghi_plans/ghi_plans.admin.plan_edit_form';

    $allow_fields = [
      'field_decimal_format',
      'field_footnotes',
      'field_link_to_fts',
      'field_max_admin_level',
      'field_operations_category',
      'field_plan_caseload',
      'field_plan_document_link',
      'field_plan_type_label_override',
      'field_plan_version_argument',
      'field_visible_on_global_pages',
      'field_focus_country_override',
    ];
    foreach (Element::children($form) as $element_key) {
      if (!in_array($element_key, $allow_fields) && $form[$element_key]['#type'] != 'actions') {
        continue;
      }
      unset($form[$element_key]['#disabled']);
    }

    // Check for required fields with unset values, extract the default value
    // from the field config and set that instead. This is a UX addition,
    // applying only to existing items.
    // Currently supported widget types are textfields and select dropdowns.
    foreach (Element::children($form) as $element_key) {
      if (strpos($element_key, 'field_') !== 0 || !empty($form[$element_key]['#disabled'])) {
        continue;
      }
      $widget = &$form[$element_key]['widget'];
      if (empty($widget['#required'])) {
        continue;
      }
      /** @var \Drupal\field\FieldConfigInterface $field */
      $field = \Drupal::entityTypeManager()->getStorage('field_config')->load('base_object.plan.' . $widget['#field_name']);
      // Get the default value for the field. This is used to extract the path
      // to the widget value and to set the #default_value in case it is NULL.
      $default_widget_value = $field->get('default_value');
      if (array_key_exists('#theme', $widget) && $widget['#theme'] == 'field_multiple_value_form') {
        // Textfields.
        $ref = &$widget;
        while (is_array($default_widget_value) && array_key_exists(array_key_first($default_widget_value), $ref)) {
          $key = array_key_first($default_widget_value);
          $ref = &$ref[$key];
          $default_widget_value = $default_widget_value[$key];
        }
        if (!array_key_exists('#default_value', $ref) || $ref['#default_value'] === NULL) {
          $ref['#default_value'] = $default_widget_value;
        }
      }
      elseif (array_key_exists('#type', $widget) && $widget['#type'] == 'select' && empty($widget['#default_value'])) {
        // Select fields.
        $widget['#default_value'] = $default_widget_value[0]['value'];
      }
    }

    if (!empty($form['field_requirements'])) {
      $form['field_requirements']['#disabled'] = TRUE;
    }
    if (!empty($form['field_funding_total'])) {
      $form['field_funding_total']['#disabled'] = TRUE;
    }
    if (!empty($form['field_funding_overall'])) {
      $form['field_funding_overall']['#disabled'] = TRUE;
    }
    $form['update_financial_data'] = [
      '#type' => 'button',
      '#value' => t('Update financial data from FTS'),
      '#ajax' => [
        'event' => 'click',
        'callback' => [self::class, 'updateFinancialData'],
      ],
      '#limit_validation_errors' => [],
      '#group' => 'group_financial_data',
      // '#weight' => 20,
      '#prefix' => '<div id="update-financial-data-wrapper">',
      '#suffix' => '</div>',
    ];
    $form['#group_children']['update_financial_data'] = 'group_financial_data';

    // Better display of the plan coordinator in the form.
    if (!empty($form['field_plan_coordinator']) && $form['field_plan_coordinator']['#disabled']) {
      $form['field_plan_coordinator']['summary'] = [
        '#type' => 'textfield',
        '#title' => $form['field_plan_coordinator']['widget']['#title'],
        '#default_value' => implode(', ', $plan->getPlanCoordinator()),
        '#disabled' => TRUE,
        '#required' => $form['field_plan_coordinator']['widget']['#required'],
      ];
      $form['field_plan_coordinator']['widget']['#access'] = FALSE;
    }

    if (!empty($form['field_focus_country_override'])) {
      // Display the field description above the map instead of below.
      $form['field_focus_country_override']['widget'][0]['value']['message'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['fieldset__description'],
        ],
        '#value' => $form['field_focus_country_override']['widget'][0]['value']['#description'],
        '#weight' => -1,
      ];
      $form['field_focus_country_override']['widget'][0]['value']['#description'] = NULL;
    }

    // Display disabled select dropdowns as simple textfields.
    foreach (Element::children($form) as $element_key) {
      if (empty($form[$element_key]['#disabled']) || ($form[$element_key]['widget']['#type'] ?? NULL) != 'select') {
        continue;
      }
      $default = $form[$element_key]['widget']['#default_value'];
      $form[$element_key]['widget']['#access'] = FALSE;
      $form[$element_key]['summary'] = [
        '#type' => 'textfield',
        '#title' => $form[$element_key]['widget']['#title'],
        '#default_value' => !empty($default) ? $form[$element_key]['widget']['#options'][array_shift($default)] : NULL,
        '#required' => $form[$element_key]['widget']['#required'],
      ];
    }
  }

  /**
   * Ajax callback to update the financial data of the plan.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response object.
   */
  public static function updateFinancialData(array &$form, FormStateInterface $form_state): AjaxResponse {
    /** @var \Drupal\ghi_plans\Entity\Plan $plan */
    $plan = $form['#entity'];

    // Fetch the data and save the plan.
    $plan->updateFinancialData();
    $plan->setSyncing(TRUE);
    $plan->save();

    // Prepare a response that updates the form fields.
    $response = new AjaxResponse();
    $update_values = [
      $form['field_requirements']['widget'][0]['value']['#attributes']['data-drupal-selector'] => $plan->getRequirements(),
      $form['field_requirements_original']['widget'][0]['value']['#attributes']['data-drupal-selector'] => $plan->getOriginalRequirements(),
      $form['field_funding_total']['widget'][0]['value']['#attributes']['data-drupal-selector'] => $plan->getTotalFunding(),
      $form['field_funding_overall']['widget'][0]['value']['#attributes']['data-drupal-selector'] => $plan->getOverallFunding(),
    ];
    foreach ($update_values as $selector => $value) {
      $formatted_value = $value !== NULL ? number_format($value, 2, '.', '') : NULL;
      $response->addCommand(new InvokeCommand('[data-drupal-selector="' . $selector . '"]', 'val', [$formatted_value]));
    }
    // Give feedback.
    $response->addCommand(new MessageCommand(t('The financial data has been updated'), '#update-financial-data-wrapper', ['type' => 'status']));
    return $response;
  }

}
