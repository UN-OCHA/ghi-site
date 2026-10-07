<?php

namespace Drupal\ghi_blocks\Update;

/**
 * Records removed deploy hooks whose names must never be reused.
 *
 * Drush records executed deploy hooks in the database, but unlike Drupal post
 * updates it provides no source-level API for declaring removed hook names.
 * This registry keeps the names available to fresh installations and is
 * enforced by RemovedDeployHookRegistryTest.
 */
final class RemovedDeployHookRegistry {

  /**
   * Removed hook names keyed by the date on which they were removed.
   */
  public const HOOKS = [
    'ghi_base_objects_deploy_base_object_bundles_admin_menu_2' => '2026-10-07',
    'ghi_base_objects_deploy_copy_geojson_source_files' => '2026-10-07',
    'ghi_base_objects_deploy_import_country_outlines_from_fallback' => '2026-10-07',
    'ghi_blocks_deploy_9001_remove_empty_layout_builder_sections' => '2026-10-07',
    'ghi_blocks_deploy_9002_merge_layout_builder_sections' => '2026-10-07',
    'ghi_blocks_deploy_9003_queue_nodes_for_plan_entity_type_replacement' => '2026-10-07',
    'ghi_blocks_deploy_9003_retire_plan_entity_types' => '2026-10-07',
    'ghi_blocks_deploy_9004_remove_field_block' => '2026-10-07',
    'ghi_blocks_deploy_9004_retire_plan_entity_types_revisions' => '2026-10-07',
    'ghi_blocks_deploy_9005_remove_field_block_revisions' => '2026-10-07',
    'ghi_blocks_deploy_9006_update_link_configuration' => '2026-10-07',
    'ghi_blocks_deploy_9006_update_link_configuration_nodes' => '2026-10-07',
    'ghi_blocks_deploy_9007_update_link_configuration_page_templates' => '2026-10-07',
    'ghi_blocks_deploy_9008_update_plan_overview_map' => '2026-10-07',
    'ghi_blocks_deploy_9009_update_funding_coverage_default_label_nodes' => '2026-10-07',
    'ghi_blocks_deploy_90010_update_funding_coverage_default_label_page_templates' => '2026-10-07',
    'ghi_blocks_deploy_9010_fix_broken_block_config' => '2026-10-07',
    'ghi_blocks_deploy_9011_remove_overridden_disclaimer_nodes' => '2026-10-07',
    'ghi_blocks_deploy_9012_remove_overridden_disclaimer_page_templates' => '2026-10-07',
    'ghi_blocks_deploy_9013_update_plan_entity_logframe_id_type' => '2026-10-07',
    'ghi_content_deploy_cleanup_migrate_map_entries' => '2026-10-07',
    'ghi_content_deploy_populate_orphaned_field' => '2026-10-07',
    'ghi_content_deploy_queue_related_articles_block_configuration_update' => '2026-10-07',
    'ghi_content_deploy_update_tag_type_data' => '2026-10-07',
    'ghi_hero_image_deploy_flush_styles' => '2026-10-07',
    'ghi_image_deploy_fix_mime_types' => '2026-10-07',
    'ghi_menu_deploy_add_pages_item_to_admin_menu' => '2026-10-07',
    'ghi_menu_deploy_adjust_admin_menu' => '2026-10-07',
    'ghi_menu_deploy_cleanup_broken_menu_items' => '2026-10-07',
    'ghi_menu_deploy_remove_global_sections_from_admin_menu' => '2026-10-07',
    'ghi_plan_clusters_deploy_update_title_overrides' => '2026-10-07',
    'ghi_plans_deploy_update_plan_operations_category' => '2026-10-07',
    'ghi_subpages_custom_deploy_move_existing_content' => '2026-10-07',
    'ghi_subpages_deploy_create_standard_subpages' => '2026-10-07',
    'ghi_subpages_deploy_delete_homepage_subpages' => '2026-10-07',
    'ghi_subpages_deploy_fix_logframe_headline_figures' => '2026-10-07',
    'ghi_subpages_deploy_queue_logframes' => '2026-10-07',
    'ghi_subpages_deploy_update_subpage_url_aliases' => '2026-10-07',
  ];

}
