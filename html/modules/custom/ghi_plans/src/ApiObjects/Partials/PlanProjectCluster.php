<?php

namespace Drupal\ghi_plans\ApiObjects\Partials;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;

/**
 * Abstraction class for a project cluster partial object.
 *
 * This kind of partial object is a stripped-down, limited-data, object that
 * appears in some specific endpoints. We map this here to provide type hinting
 * and abstracted data access.
 */
class PlanProjectCluster extends BaseObject {

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
      'name' => $data->name,
      'icon' => $data->value->icon,
    ];
  }

  /**
   * Get the icon for the cluster.
   *
   * @return string
   *   The icon string.
   */
  public function getIcon() {
    return $this->icon;
  }

}
