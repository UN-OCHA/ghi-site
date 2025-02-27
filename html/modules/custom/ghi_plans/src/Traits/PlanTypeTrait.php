<?php

namespace Drupal\ghi_plans\Traits;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\hpc_common\Helpers\StringHelper;
use Drupal\hpc_common\Helpers\TaxonomyHelper;

/**
 * Trait to help with plan types.
 */
trait PlanTypeTrait {

  use StringTranslationTrait;

  /**
   * Sort the given plans by plan type and name.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[] $plans
   *   An array of plan objects.
   * @param bool $use_shortname
   *   Whether to use the shortname for sorting or not.
   */
  public function sortPlansByPlanType(array &$plans, $use_shortname = FALSE) {
    // Sort everything first by plan type, then by plan name.
    $type_order = $this->getAvailablePlanTypes();

    $grouped_plans = [];
    foreach ($type_order as $plan_type) {
      $plan_type_key = $plan_type;

      // Create a list of all plans for this plan type.
      foreach ($plans as $plan) {
        if (!$plan->isType($plan_type)) {
          continue;
        }
        if (empty($grouped_plans[$plan_type_key])) {
          $grouped_plans[$plan_type_key] = [];
        }
        $grouped_plans[$plan_type_key][] = $plan;
      }
      // And sort it by plan name.
      if (!empty($grouped_plans[$plan_type_key])) {
        ArrayHelper::sortObjectsByCallback($grouped_plans[$plan_type_key], function ($item) use ($use_shortname) {
          return $use_shortname ? $item->getShortName() : $item->getName();
        }, EndpointQuery::SORT_ASC, SORT_STRING);
      }
    }

    $plans = [];
    foreach ($grouped_plans as $group) {
      foreach ($group as $plan) {
        $plans[$plan->id()] = $plan;
      }
    }
  }

  /**
   * Get the available plan types.
   *
   * @param bool $include_description
   *   Whether to include the term description.
   *
   * @return array
   *   The plan types as a simple id-label pair array.
   */
  public function getAvailablePlanTypes($include_description = FALSE) {
    $terms = TaxonomyHelper::loadMultipleTermsByVocabulary('plan_type');
    if (empty($terms)) {
      return [];
    }
    // Sort by weight.
    usort($terms, function ($a, $b) {
      return $a->getWeight() - $b->getWeight();
    });
    return array_map(function ($term) use ($include_description) {
      /** @var \Drupal\taxonomy\Entity\Term $term */
      if ($include_description) {
        return $term->label() . (!empty($term->getDescription()) ? ' (' . $term->getDescription() . ')' : '');
      }
      return $term->label();
    }, $terms);
  }

  /**
   * Get a plan type taxonomy entity by name.
   *
   * @param string $name
   *   The term name to search for.
   *
   * @return \Drupal\taxonomy\Entity\Term|null
   *   The term entity or NULL if not found.
   */
  public function getTermObjectByName($name) {
    $terms = TaxonomyHelper::loadMultipleTermsByVocabulary('plan_type');
    foreach ($terms as $term) {
      if ($term->label() != $name) {
        continue;
      }
      return $term;
    }
    return NULL;
  }

  /**
   * Get the plan type short name.
   *
   * @param string $name
   *   The original name of the plan type.
   *
   * @return string
   *   A short name for the given plan type name.
   */
  public function getPlanTypeShortName($name) {
    return StringHelper::getAbbreviation($name);
  }

}
