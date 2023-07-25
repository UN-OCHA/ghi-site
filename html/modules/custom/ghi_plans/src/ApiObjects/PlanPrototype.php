<?php

namespace Drupal\ghi_plans\ApiObjects;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;

/**
 * Abstraction class for API plan prototype objects.
 */
class PlanPrototype extends BaseObject {

  /**
   * Map the raw data.
   *
   * @return object
   *   An object with the mapped data.
   */
  protected function map() {
    $data = $this->getRawData();

    $plan_id = reset($data)->planId;
    $items = [];
    foreach ($data as $item) {
      $items[$item->orderNumber] = new EntityPrototype($item);
    }
    ksort($items);

    return (object) [
      'plan_id' => $plan_id,
      'items' => $items,
    ];

  }

  /**
   * Get the entity prototypes that make up this plan prototype.
   *
   * @return \Drupal\ghi_plans\ApiObjects\EntityPrototype[]
   *   An array of entity prototypes.
   */
  public function getEntityPrototypes() {
    return $this->items;
  }

}
