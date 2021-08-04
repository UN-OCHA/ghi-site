<?php

namespace Drupal\ghi_subpages\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides an event subscriber that alters routes.
 *
 * This is used to remove the custom access requirement for the publishcontent
 * modules publish route, so that it can be used even if the module is
 * configured to not show local tasks.
 *
 * @see ghi_subpages_local_tasks_alter()
 *
 * @package Drupal\ghi_subpages
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Remove check for "Publish and unpublish via local task" config.
    // Normally, the publish route only works if that config is set on
    // /admin/config/workflow/publishcontent, but we want to use this route
    // from other place, so we specifically disable the custom access check.
    if ($route = $collection->get('entity.node.publish')) {
      $requirements = $route->getRequirements();
      unset($requirements['_custom_access']);
      $route->setRequirements($requirements);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[RoutingEvents::ALTER] = 'onAlterRoutes';
    return $events;
  }

}
