<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\ApiObjects\Facts\GhoFact;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Retrieves GHO facts and preserves their dimensional references.
 */
#[FabricQuery(
  id: 'gho_fact',
  label: new TranslatableMarkup('GHO fact query'),
)]
class GhoFactQuery extends FabricQueryBase {

  /**
   * Gets facts for multiple plans, grouped by plan ID.
   *
   * @param int[] $plan_ids
   *   The Fabric plan IDs.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Facts\GhoFact[][]
   *   Facts keyed by plan ID and then fact ID.
   */
  public function getFactsForPlans(array $plan_ids): array {
    $plan_ids = array_values(array_unique(array_filter(array_map('intval', $plan_ids))));
    if (empty($plan_ids)) {
      return [];
    }
    sort($plan_ids);
    $items = $this->fabricClient->createQuery('ghoFacts', GhoFact::getGraphQlItems())
      ->setFilter('PlanId', $plan_ids)
      ->execute() ?: [];
    $facts = [];
    foreach ($items as $item) {
      $fact = new GhoFact($item);
      $facts[$fact->getPlanId()][$fact->id()] = $fact;
    }
    return $facts;
  }

}
