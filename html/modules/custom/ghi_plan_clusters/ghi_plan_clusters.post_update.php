<?php

/**
 * @file
 * Post update functions for GHI Plan Clusters.
 */

use Drupal\ghi_plan_clusters\Entity\PlanCluster;

/**
 * Assure section references for plan clusters.
 */
function ghi_plan_clusters_post_update_assure_section_reference() {
  $nids = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->getQuery()
    ->condition('type', 'plan_cluster')
    ->notExists('field_entity_reference')
    ->execute();
  if (empty($nids)) {
    return;
  }
  foreach (PlanCluster::loadMultiple($nids) as $plan_cluster) {
    // Just save, the reference is set in ghi_plan_clusters_node_presave().
    $plan_cluster->save();
  }
}
