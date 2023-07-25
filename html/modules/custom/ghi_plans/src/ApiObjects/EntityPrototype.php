<?php

namespace Drupal\ghi_plans\ApiObjects;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;

/**
 * Abstraction class for API entity prototype objects.
 */
class EntityPrototype extends BaseObject {

  /**
   * Map the raw data.
   *
   * @return object
   *   An object with the mapped data.
   */
  protected function map() {
    $data = $this->getRawData();

    return (object) [
      'id' => $data->id,
      'ref_code' => $data->refCode,
      'type' => $data->type,
      'name_singular' => $data->value->name->en->singular,
      'name_plural' => $data->value->name->en->plural,
      'order_number' => $data->orderNumber,
      'can_support' => $data->value->canSupport ?? [],
      'children' => $data->value->possibleChildren ?? [],
    ];

  }

  /**
   * Get the plural name for the entity prototype.
   *
   * @return string
   *   The plural name.
   */
  public function getPluralName() {
    return $this->name_plural;
  }

  /**
   * Get the ref code for the entity prototype.
   *
   * @return string
   *   The ref code.
   */
  public function getRefCode() {
    return $this->ref_code;
  }

}
