<?php

namespace Drupal\ghi_blocks\MapObjects;

/**
 * Class for cluster map objects.
 */
class ClusterMapObject extends BaseMapObject {

  /**
   * Get the icon for a cluster.
   *
   * @return string
   *   The icon string for the cluster.
   */
  public function getIcon() {
    return $this->data['icon'] ?? NULL;
  }

}
