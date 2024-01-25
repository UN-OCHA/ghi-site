<?php

namespace Drupal\ghi_homepage\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\ghi_homepage\Entity\Homepage;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides an event subscriber that alters routes.
 *
 * @package Drupal\ghi_homepage
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {

    // Add a custom route based on the generic node.add route, in order to use
    // our Homepage Wizard form class for the creation of homepages.
    if ($route = $collection->get('node.add')) {
      $wizard_route = clone $route;
      $wizard_route->setPath('node/add/' . Homepage::BUNDLE);
      $wizard_route->setRequirement('_entity_create_access', 'node:' . Homepage::BUNDLE);
      $wizard_route->setDefault('_form', '\Drupal\ghi_homepage\Form\HomepageWizard');
      $wizard_route->setDefault('node_type', Homepage::BUNDLE);
      $collection->add('ghi_homepage.wizard.homepage', $wizard_route);
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
