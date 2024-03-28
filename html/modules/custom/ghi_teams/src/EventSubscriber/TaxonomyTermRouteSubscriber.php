<?php

namespace Drupal\ghi_teams\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\ghi_teams\Controller\TaxonomyTermController;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route subscriber for taxonomy pages.
 *
 * Make sure that the taxonomy term pages are displayed using the admin theme.
 * Set title callbacks for term form pages.
 *
 * @see \Drupal\ghi_teams\Controller\TaxonomyTermController
 */
class TaxonomyTermRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Sets the _admin_route for the canonical taxonomy term route.
    if ($route = $collection->get('entity.taxonomy_term.canonical')) {
      $route->setOption('_admin_route', TRUE);
      $route->setRequirement('_custom_access', '\Drupal\ghi_teams\Controller\TaxonomyTermController::access');
    }
    if ($route = $collection->get('entity.taxonomy_term.add_form')) {
      $route->setDefault('_title_callback', TaxonomyTermController::class . '::addTitle');
    }
    if ($route = $collection->get('entity.taxonomy_term.edit_form')) {
      $route->setDefault('_title_callback', TaxonomyTermController::class . '::editTitle');
    }
  }

}
