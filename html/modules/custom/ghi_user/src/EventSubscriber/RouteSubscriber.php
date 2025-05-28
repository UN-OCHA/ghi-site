<?php

namespace Drupal\ghi_user\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides an event subscriber that alters routes.
 *
 * @package Drupal\ghi_user
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {

    // Restrict access to the social profiles to admins.
    if ($route = $collection->get('social_auth.user.profiles')) {
      $route->addOptions(['_admin_route' => TRUE]);
      $route->addRequirements(['_permission' => 'administer users']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    $events[RoutingEvents::ALTER] = 'onAlterRoutes';
    return $events;
  }

}
