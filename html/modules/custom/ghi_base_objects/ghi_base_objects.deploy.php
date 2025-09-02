<?php

/**
 * @file
 * Deploy functions for GHI Base objects.
 */

use Drupal\Core\Url;
use Drupal\ghi_geojson\GeoJson;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Update links in the admin menu.
 */
function ghi_base_objects_deploy_base_object_bundles_admin_menu_2() {
  $types = \Drupal::entityTypeManager()->getStorage('base_object_type')->loadMultiple();
  usort($types, function ($a, $b) {
    return strnatcasecmp($a->label(), $b->label());
  });
  foreach (array_values($types) as $weight => $type) {
    $route_name = 'view.base_objects.' . $type->id();
    try {
      \Drupal::service('router.route_provider')->getRouteByName($route_name);
    }
    catch (Exception $e) {
      continue;
    }
    MenuLinkContent::create([
      'title' => $type->label(),
      'link' => [
        'uri' => 'internal:' . Url::fromRoute($route_name)->toString(),
      ],
      'menu_name' => 'admin',
      'parent' => 'base_object.collection',
      'weight' => $weight,
    ])->save();
  }
}

/**
 * Copy geojson source files into public file directory.
 */
function ghi_base_objects_deploy_copy_geojson_source_files() {
  $fileSystem = new Filesystem();
  $source_dir = \Drupal::moduleHandler()->getModule('ghi_base_objects')->getPath() . '/assets/geojson/';
  $target_dir = GeoJson::GEOJSON_SOURCE_DIR;
  $fileSystem->mirror($source_dir, $target_dir);
}
