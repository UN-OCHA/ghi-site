<?php

namespace Drupal\ghi_sections\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\ghi_sections\Entity\Section;
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
    // our Section Wizard form class for the creation of sections.
    if ($route = $collection->get('node.add')) {
      $wizard_route = clone $route;
      $wizard_route->setPath('node/add/' . Section::BUNDLE);
      $wizard_route->setRequirement('_entity_create_access', 'node:' . Section::BUNDLE);
      $wizard_route->setDefault('_form', '\Drupal\ghi_sections\Form\SectionWizard');
      $wizard_route->setDefault('node_type', Section::BUNDLE);
      $collection->add('ghi_sections.wizard.section', $wizard_route);
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
