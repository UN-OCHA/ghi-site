<?php

namespace Drupal\ghi_sections\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides an event subscriber that alters routes.
 *
 * @package Drupal\ghi_sections
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {

    // Add a custom route based on the generic node.add route, in order to use
    // our Section Wizard form class for the creation of a new section.
    if ($route = $collection->get('node.add')) {
      $wizard_route = clone $route;
      $wizard_route->setPath('node/add/section');
      $wizard_route->setDefault('_form', '\Drupal\ghi_sections\Form\SectionWizard');
      $wizard_route->setDefault('node_type', 'section');
      $collection->add('ghi_sections.wizard', $wizard_route);
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
