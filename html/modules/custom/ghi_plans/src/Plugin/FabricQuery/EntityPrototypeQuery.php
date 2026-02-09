<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\ApiObjects\Prototypes\EntityPrototype;
use Drupal\ghi_plans\ApiObjects\Prototypes\PlanPrototype;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'entity_prototype' fabric query.
 */
#[FabricQuery(
  id: 'entity_prototype',
  label: new TranslatableMarkup('Entity prototype query'),
)]
class EntityPrototypeQuery extends FabricQueryBase {

  /**
   * Get an entity prototype by its id.
   *
   * @param int $prototype_id
   *   The entity prototype id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Prototypes\EntityPrototype|null
   *   The entity prototype object or NULL if not found.
   */
  public function getPrototype(int $prototype_id): ?EntityPrototype {
    // Get the prototype data.
    $prototypes = $this->fabricClient->createQuery('entityPrototypes', EntityPrototype::getGraphQlItems())
      ->setFilter('Id', $prototype_id)
      ->execute();
    if (empty($prototypes)) {
      return NULL;
    }
    $prototype = reset($prototypes);
    return $prototype ? new EntityPrototype($prototype) : NULL;
  }

  /**
   * Get all entity prototypes for a plan.
   *
   * @param int $plan_id
   *   The id of the plan to which a prototype belongs.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Prototypes\PlanPrototype|null
   *   The processed plan prototype object or NULL.
   */
  public function getPlanPrototype(int $plan_id): ?PlanPrototype {
    // Get the prototypes.
    $prototypes = $this->fabricClient->createQuery('entityPrototypes', EntityPrototype::getGraphQlItems())
      ->setFilter('PlanId', $plan_id)
      ->execute();
    if (empty($prototypes)) {
      return NULL;
    }
    return new PlanPrototype($prototypes);
  }

}
