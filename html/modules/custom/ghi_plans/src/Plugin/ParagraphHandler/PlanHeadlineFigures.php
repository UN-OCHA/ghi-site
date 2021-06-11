<?php

namespace Drupal\ghi_plans\Plugin\ParagraphHandler;

/**
 * Entity types.
 *
 * @ParagraphHandler(
 *   id = "plan_headline_figures",
 *   label = @Translation("Plan headline figures"),
 *   data_sources = {
 *     "entities" = {
 *       "service" = "ghi_plans.plan_entities_query"
 *     },
 *     "project_search" = {
 *       "service" = "ghi_plans.plan_project_search_query"
 *     },
 *     "funding_summary" = {
 *       "service" = "ghi_plans.plan_funding_summary_query"
 *     },
 *     "cluster_summary" = {
 *       "service" = "ghi_plans.plan_cluster_summary_query"
 *     }
 *   },
 * )
 */
class PlanHeadlineFigures extends PlanBaseClass {

  /**
   * {@inheritdoc}
   */
  const KEY = 'plan_headline_figures';

  /**
   * Define the maximum number of items that this widget can hold.
   */
  const MAX_ITEMS = 6;

  /**
   * {@inheritdoc}
   */
  public function preprocess(array &$variables, array $element) {
    parent::preprocess($variables, $element);

    if (!isset($this->parentEntity->field_original_id) || $this->parentEntity->field_original_id->isEmpty()) {
      return;
    }

    // @todo Implement output rendering.
  }

  /**
   * {@inheritdoc}
   */
  public function widgetAlter(&$element, &$form_state, $context) {
    parent::widgetAlter($element, $form_state, $context);

    if (!isset($this->parentEntity->field_original_id) || $this->parentEntity->field_original_id->isEmpty()) {
      return;
    }

    $subform = &$element['subform'];
    $config = $this->getConfig($form_state);

    $subform['items'] = [
      '#type' => 'configuration_container',
      '#title' => $this->t('Configured headline figures'),
      '#plan_context' => $this->parentEntity,
      '#default_value' => array_key_exists('items', $config) ? $config['items'] : [],
      '#allowed_item_types' => $this->getAllowedItemTypes(),
      '#preview' => [
        'columns' => [
          'label' => $this->t('Label'),
          'value' => $this->t('Value'),
        ],
        'callback_context' => [static::class, 'getContext'],
      ],
      // @codingStandardsIgnoreStart
      // '#widget_preview_callback' => 'hpc_content_panes_plan_headline_figures_build_widget',
      // '#widget_preview_arguments' => array(
      //   'subtype' => $subtype,
      // ),
      // @codingStandardsIgnoreEnd
      '#max_items' => self::MAX_ITEMS,
    ];
  }

  /**
   * Get the context for this plugin.
   *
   * @return array
   *   An array of context objects.
   */
  public function getContext() {
    return [];
  }

  /**
   * Get the allowed item types for this element.
   *
   * @return array
   *   An array with the allowed item types, keyed by the plugin id, with the
   *   value being an optional configuration array for the plugin.
   */
  public function getAllowedItemTypes() {
    $item_types = [
      'plan_entities_counter' => [],
    ];
    return $item_types;
  }

}
