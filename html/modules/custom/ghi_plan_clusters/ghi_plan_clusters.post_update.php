<?php

/**
 * @file
 * Post update functions for GHI Plan Clusters.
 */

use Drupal\ghi_plan_clusters\Entity\PlanCluster;
use Drupal\ghi_plan_clusters\Entity\PlanClusterInterface;

/**
 * Assure section references for plan clusters.
 */
function ghi_plan_clusters_post_update_assure_section_reference() {
  $nids = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->getQuery()
    ->condition('type', PlanClusterInterface::BUNDLE)
    ->notExists('field_entity_reference')
    ->accessCheck(FALSE)
    ->execute();
  if (empty($nids)) {
    return;
  }
  foreach (PlanCluster::loadMultiple($nids) as $plan_cluster) {
    // Just save, the reference is set in ghi_plan_clusters_node_presave().
    $plan_cluster->save();
  }
}

/**
 * Remove plan cluster nodes that are missing a base object.
 */
function ghi_plan_clusters_post_update_remove_invalid_plan_clusters() {
  // Load nids of plan_cluster nodes without a base object.
  $nids = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->getQuery()
    ->condition('type', PlanClusterInterface::BUNDLE)
    ->condition('field_base_object', NULL, 'IS NULL')
    ->accessCheck(FALSE)
    ->execute();
  if (empty($nids)) {
    return;
  }
  foreach (PlanCluster::loadMultiple($nids) as $plan_cluster) {
    // Delete the broken plan cluster node.
    $plan_cluster->delete();
  }
}
