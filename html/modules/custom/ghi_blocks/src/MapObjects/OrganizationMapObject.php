<?php

namespace Drupal\ghi_blocks\MapObjects;

/**
 * Class for organization map objects.
 */
class OrganizationMapObject extends BaseMapObject {

  /**
   * Get the projects for the map object.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Project[]
   *   An array of project objects.
   */
  public function getProjects() {
    return $this->data['projects'] ?? [];
  }

}
