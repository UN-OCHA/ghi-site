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

    // @todo Implement configuration interface.
  }

}
