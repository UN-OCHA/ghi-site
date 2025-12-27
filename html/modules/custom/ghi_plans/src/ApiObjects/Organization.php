<?php

namespace Drupal\ghi_plans\ApiObjects;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;
use Drupal\hpc_common\Helpers\CommonHelper;

/**
 * Abstraction class for API organization objects.
 */
class Organization extends BaseObject {

  const GRAPHQL_ITEMS = '
    Id
    Name
    Url
  ';

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
      'id' => $data->Id ?? ($data->id ?? NULL),
      'name' => $data->Name ?? ($data->name ?? NULL),
      'url' => CommonHelper::assureWellFormedUri($data->Url ?? ($data->url ?? NULL)),
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
