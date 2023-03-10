<?php

namespace Drupal\ghi_teams\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Sets the _admin_route for all taxonomy term routes.
 *
 * Make sure that the taxonomy term pages are displayed using the admin theme.
 */
class TaxonomyTermRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('entity.taxonomy_term.canonical')) {
      $route->setOption('_admin_route', TRUE);
    }
  }

}
