<?php

namespace Drupal\ghi_subpages_custom\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\ghi_subpages_custom\CustomSubpageManager;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides an event subscriber that alters routes.
 *
 * @package Drupal\ghi_subpages_custom
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {

    // Add a custom route based on the generic node.add route, in order to use
    // our CustomSubpageWizard form class for the creation of custom subpages.
    if ($route = $collection->get('node.add')) {
      $wizard_route = clone $route;
      $wizard_route->setPath('node/add/' . CustomSubpageManager::BUNDLE);
      $wizard_route->setRequirement('_entity_create_access', 'node:' . CustomSubpageManager::BUNDLE);
      $wizard_route->setDefault('_form', '\Drupal\ghi_subpages_custom\Form\CustomSubpageWizard');
      $wizard_route->setDefault('node_type', CustomSubpageManager::BUNDLE);
      $collection->add('ghi_subpages_custom.wizard.custom_subpage', $wizard_route);
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
