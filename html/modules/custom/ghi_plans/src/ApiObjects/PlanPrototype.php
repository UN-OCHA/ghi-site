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
      $items[$item->orderNumber] = (object) [
        'id' => $item->id,
        'ref_code' => $item->refCode,
        'type' => $item->type,
        'name_singular' => $item->value->name->en->singular,
        'name_plural' => $item->value->name->en->plural,
        'order_number' => $item->orderNumber,
        'can_support' => $item->value->canSupport ?? [],
        'children' => $item->value->possibleChildren ?? [],
      ];
    }
    ksort($items);

    return (object) [
      'plan_id' => $plan_id,
      'items' => $items,
    ];
  }

}
