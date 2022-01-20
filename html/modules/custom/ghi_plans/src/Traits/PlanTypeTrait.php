<?php

namespace Drupal\ghi_plans\Traits;

use Drupal\hpc_common\Helpers\TaxonomyHelper;

/**
 * Trait to help with plan types.
 */
trait PlanTypeTrait {

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
    uasort($terms, function ($a, $b) {
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
   *   The term name to retrieve.
   * @param bool $include_totals
   *   Optional: Whether the term should have the include flag set.
   *
   * @return \Drupal\taxonomy\Entity\Term|null
   *   The term entity or NULL if not found.
   */
  public function getTermObjectByName($name, $include_totals = NULL) {
    $terms = TaxonomyHelper::loadMultipleTermsByVocabulary('plan_type');
    foreach ($terms as $term) {
      if ($term->label() != $name) {
        continue;
      }
      if ($include_totals !== NULL && $term->get('field_included_in_totals')->value != $include_totals) {
        continue;
      }
      return $term;
    }
    return NULL;
  }

}
