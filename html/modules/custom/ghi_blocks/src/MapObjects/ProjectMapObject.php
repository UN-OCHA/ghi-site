<?php

namespace Drupal\ghi_blocks\MapObjects;

/**
 * Class for project map objects.
 */
class ProjectMapObject extends BaseMapObject {

  /**
   * Get the clusters for the map object.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Partials\PlanProjectCluster[]
   *   An array of project cluster objects.
   */
  public function getClusters() {
    return $this->data['clusters'] ?? [];
  }

}
