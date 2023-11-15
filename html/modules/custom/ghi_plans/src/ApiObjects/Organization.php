<?php

namespace Drupal\ghi_plans\ApiObjects;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;
use Drupal\hpc_common\Helpers\CommonHelper;

/**
 * Abstraction class for API organization objects.
 */
class Organization extends BaseObject {

  /**
   * A list of clusters.
   *
   * @var array
   */
  public $clusters;

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
      'url' => CommonHelper::assureWellFormedUri($data->url),
    ];
  }

  /**
   * Get the names of the associated clusters.
   *
   * @return string[]
   *   An array of cluster names.
   */
  public function getClusterNames() {
    return array_map(function ($cluster) {
      return $cluster->name;
    }, $this->map->clusters);
  }

}
