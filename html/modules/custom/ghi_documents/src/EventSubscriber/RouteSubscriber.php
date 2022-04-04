<?php

namespace Drupal\ghi_documents\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\ghi_documents\DocumentManager;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides an event subscriber that alters routes.
 *
 * @package Drupal\ghi_documents
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {

    // Add a custom route based on the generic node.add route, in order to use
    // our Document Wizard form class for the creation of documents.
    if ($route = $collection->get('node.add')) {
      $wizard_route = clone $route;
      $wizard_route->setPath('node/add/' . DocumentManager::DOCUMENT_BUNDLE);
      $wizard_route->setRequirement('_entity_create_access', 'node:document');
      $wizard_route->setDefault('_form', '\Drupal\ghi_documents\Form\DocumentWizard');
      $wizard_route->setDefault('node_type', DocumentManager::DOCUMENT_BUNDLE);
      $collection->add('ghi_documents.wizard.document', $wizard_route);
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
