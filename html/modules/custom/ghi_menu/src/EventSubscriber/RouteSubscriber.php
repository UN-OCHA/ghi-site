<?php

namespace Drupal\ghi_menu\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides an event subscriber that alters routes.
 *
 * @package Drupal\ghi_menu
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Allow access to /admin/structure/taxonomy.
    // The list of vocabularies there is then handled by
    // ghi_menu_form_taxonomy_overview_vocabularies_alter() to hide
    // vocabularies where the user lacks permissions to edit.
    if ($route = $collection->get('entity.taxonomy_vocabulary.collection')) {
      $route->setRequirement('_permission', 'access taxonomy overview+access administration pages');
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
