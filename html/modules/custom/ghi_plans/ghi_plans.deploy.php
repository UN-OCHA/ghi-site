<?php

/**
 * @file
 * Contains deploy functions for the GHI Plans module.
 */

use Drupal\ghi_plans\Entity\Plan;

/**
 * Backfill the explicit Country Module source on existing plans.
 */
function ghi_plans_deploy_10001_backfill_plan_data_source(&$sandbox) {
  $storage = \Drupal::entityTypeManager()->getStorage('base_object');
  $plans = $storage->loadByProperties(['type' => 'plan']);
  $updated = 0;
  foreach ($plans as $plan) {
    if (!$plan instanceof Plan || !$plan->hasField('field_plan_data_source') || !$plan->get('field_plan_data_source')->isEmpty()) {
      continue;
    }
    $plan->set('field_plan_data_source', Plan::DATA_SOURCE_PLAN);
    $plan->setSyncing(TRUE);
    $plan->save();
    $updated++;
  }
  return t('Set the Country Module data source on @count existing plan(s).', [
    '@count' => $updated,
  ]);
}
