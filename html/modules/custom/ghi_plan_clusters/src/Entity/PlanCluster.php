<?php

namespace Drupal\ghi_plan_clusters\Entity;

use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\SubpageNode;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;

/**
 * Bundle class for plan cluster nodes.
 */
class PlanCluster extends SubpageNode implements SubpageNodeInterface {

  /**
   * {@inheritdoc}
   */
  public function getParentNode() {
    $entity = $this->getPlanClusterManager()->loadSectionForClusterNode($this);
    return $entity instanceof SectionNodeInterface ? $entity : NULL;
  }

  /**
   * Get the plan cluster manager.
   *
   * @return \Drupal\ghi_plan_clusters\PlanClusterManager
   *   The plan cluster manager service.
   */
  private static function getPlanClusterManager() {
    return \Drupal::service('ghi_plan_clusters.manager');
  }

}
