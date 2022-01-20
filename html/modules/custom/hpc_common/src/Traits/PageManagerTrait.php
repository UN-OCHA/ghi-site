<?php

namespace Drupal\hpc_common\Traits;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\page_manager\Entity\PageVariant;
use Symfony\Component\HttpFoundation\Request;

/**
 * Some helper functions for page manager support.
 */
trait PageManagerTrait {

  /**
   * Get the current page variant from the given route match object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   *
   * @return \Drupal\page_manager\Entity\PageVariant|null
   *   The page variant if found.
   */
  private function getCurrentPageVariant(Request $request, RouteMatchInterface $route_match) {

    if ($request->attributes->has('page_manager_page_variant')) {
      return $request->attributes->get('page_manager_page_variant');
    }

    // Otherwise we might be in the page manager config UI.
    $variant_id = NULL;
    $page_parameters = $route_match->getRawParameters()->all();
    if (array_key_exists('machine_name', $page_parameters) && array_key_exists('step', $page_parameters)) {
      // The step parameter looks like this and holds the variant id:
      // page_variant__homepage-layout_builder-0__layout_builder
      // The variant id in this case is "homepage-layout_builder-0".
      $variant_id = explode('__', $page_parameters['step'])[1];
    }
    elseif (array_key_exists('section_storage_type', $page_parameters) && $page_parameters['section_storage_type'] == 'page_manager') {
      $variant_id = $page_parameters['section_storage'];
    }

    return $variant_id ? PageVariant::load($variant_id) : NULL;
  }

}
