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
    // our Section Wizard form class for the creation of sections and global
    // sections.
    if ($route = $collection->get('node.add')) {
      $wizard_route = clone $route;
      $wizard_route->setPath('node/add/section');
      $wizard_route->setRequirement('_entity_create_access', 'node:section');
      $wizard_route->setDefault('_form', '\Drupal\ghi_sections\Form\SectionWizard');
      $wizard_route->setDefault('node_type', 'section');
      $collection->add('ghi_sections.wizard.section', $wizard_route);

      $wizard_route = clone $route;
      $wizard_route->setPath('node/add/global_section');
      $wizard_route->setRequirement('_entity_create_access', 'node:global_section');
      $wizard_route->setDefault('_form', '\Drupal\ghi_sections\Form\GlobalSectionWizard');
      $wizard_route->setDefault('node_type', 'global_section');
      $collection->add('ghi_sections.wizard.global_section', $wizard_route);

      $wizard_route = clone $route;
      $wizard_route->setPath('node/add/homepage');
      $wizard_route->setRequirement('_entity_create_access', 'node:homepage');
      $wizard_route->setDefault('_form', '\Drupal\ghi_sections\Form\HomepageSectionWizard');
      $wizard_route->setDefault('node_type', 'homepage');
      $collection->add('ghi_sections.wizard.homepage', $wizard_route);
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
