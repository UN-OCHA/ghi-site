<?php

/**
 * @file
 * Contains deploy functions for the GHI Plans module.
 */

/**
 * Update the plan operations category for the main operations menu.
 */
function ghi_plans_deploy_update_plan_operations_category(&$sandbox) {
  $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  $base_object_storage = \Drupal::entityTypeManager()->getStorage('base_object');

  /** @var \Drupal\taxonomy\TermInterface[] $terms */
  $terms = $term_storage->loadByProperties([
    'vid' => 'operations_category',
    'name' => 'Country plans',
  ]);
  $hrp_term = count($terms) ? reset($terms) : NULL;
  if (!$hrp_term) {
    $hrp_term = $term_storage->create([
      'vid' => 'operations_category',
      'name' => 'Country plans',
      'status' => TRUE,
    ]);
    $hrp_term->save();
  }

  /** @var \Drupal\taxonomy\TermInterface[] $terms */
  $terms = $term_storage->loadByProperties([
    'vid' => 'operations_category',
    'name' => 'Regional plans',
  ]);
  $regional_term = count($terms) ? reset($terms) : NULL;

  /** @var \Drupal\ghi_plans\Entity\Plan[] $plan_objects */
  $plan_objects = $base_object_storage->loadByProperties([
    'type' => 'plan',
  ]);
  foreach ($plan_objects as $plan_object) {
    $category = $plan_object->getOperationsCategory();
    if (!$category) {
      continue;
    }
    if ($regional_term && $category->id() == $regional_term->id()) {
      continue;
    }
    $plan_object->get('field_operations_category')->target_id = $hrp_term->id();
    $plan_object->setSyncing(TRUE);
    $plan_object->save();
  }

  $terms = $term_storage->loadByProperties([
    'vid' => 'operations_category',
  ]);
  foreach ($terms as $term) {
    if ($regional_term && $term->id() == $regional_term->id()) {
      continue;
    }
    if ($hrp_term && $term->id() == $hrp_term->id()) {
      continue;
    }
    $term->delete();
  }
}
