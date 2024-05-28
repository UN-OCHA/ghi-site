<?php

/**
 * @file
 * Contains deploy functions for the GHI Plan Cluster module.
 */

use Drupal\ghi_plan_clusters\Entity\PlanCluster;
use Drupal\ghi_plan_clusters\Entity\PlanClusterInterface;

/**
 * Set the title override of plan cluster nodes for all overridden nodes.
 */
function ghi_plan_clusters_deploy_update_title_overrides(&$sandbox) {
  // Load all plan_cluster nodes.
  $nids = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->getQuery()
    ->condition('type', PlanClusterInterface::BUNDLE)
    ->accessCheck(FALSE)
    ->execute();
  if (empty($nids)) {
    return;
  }
  foreach (PlanCluster::loadMultiple($nids) as $plan_cluster) {
    // Delete the broken plan cluster node.
    $base_object = $plan_cluster->getBaseObject();
    if (!$base_object) {
      continue;
    }
    $current_node_title = (string) $plan_cluster->label();
    $current_object_title = (string) $base_object->label();
    if ($current_object_title == $current_node_title) {
      continue;
    }
    $plan_cluster->setTitleOverride($current_node_title);
    $plan_cluster->setTitle($current_object_title);
    $plan_cluster->setNewRevision(FALSE);
    $plan_cluster->setSyncing(TRUE);
    $plan_cluster->save();
  }

}
