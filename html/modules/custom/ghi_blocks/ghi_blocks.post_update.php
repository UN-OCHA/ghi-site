<?php

/**
 * @file
 * Contains post update functions for the GHI Blocks module.
 */

/**
 * Remove the unused latest-plan-data global setting.
 */
function ghi_blocks_post_update_9004(): string {
  $config = \Drupal::configFactory()->getEditable('ghi_blocks.global_settings');
  $settings = $config->getRawData();
  $changed = FALSE;

  foreach ($settings as $year => $year_config) {
    if (!is_array($year_config) || !array_key_exists('use_latest_plan_data', $year_config)) {
      continue;
    }
    unset($year_config['use_latest_plan_data']);
    $config->set($year, $year_config);
    $changed = TRUE;
  }

  if ($changed) {
    $config->save();
  }

  return (string) t('Removed the unused latest plan data global setting.');
}

/**
 * Implements hook_removed_post_updates().
 */
function ghi_blocks_removed_post_updates(): array {
  return [
    'ghi_blocks_post_update_9000' => 'GHI v1.21.0',
    'ghi_blocks_post_update_9001' => 'GHI v1.21.0',
    'ghi_blocks_post_update_9002' => 'GHI v1.21.0',
    'ghi_blocks_post_update_9003' => 'GHI v1.21.0',
  ];
}
