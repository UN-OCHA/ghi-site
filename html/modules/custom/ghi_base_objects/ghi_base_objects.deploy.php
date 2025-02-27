<?php

/**
 * @file
 * Deploy functions for GHI Base objects.
 */

use Drupal\Core\Url;
use Drupal\menu_link_content\Entity\MenuLinkContent;

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
 * Import country outlines from the fallback source.
 */
function ghi_base_objects_deploy_import_country_outlines_from_fallback(&$sandbox) {
  /** @var \Drupal\hpc_api\Query\EndpointQueryManager $endpoint_query_manager */
  $endpoint_query_manager = \Drupal::service('plugin.manager.endpoint_query_manager');
  /** @var \Drupal\ghi_base_objects\Plugin\EndpointQuery\CountryQuery $country_query */
  $country_query = $endpoint_query_manager->createInstance('country_query');
  $countries = $country_query->getCountries();
  foreach ($countries as $country) {
    \Drupal::queue('ghi_base_objects_download_country_geojson')->createItem((object) [
      'country_id' => $country->id(),
    ]);
  }
  return (string) t('Enqueued @total countries for downloading their GeoJson country outline.', [
    '@total' => \Drupal::queue('ghi_base_objects_download_country_geojson')->numberOfItems(),
  ]);
}
