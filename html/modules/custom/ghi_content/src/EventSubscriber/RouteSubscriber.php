<?php

namespace Drupal\ghi_content\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\ContentManager\DocumentManager;
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
      $wizard_route->setPath('node/add/' . ArticleManager::ARTICLE_BUNDLE);
      $wizard_route->setRequirement('_entity_create_access', 'node:article');
      $wizard_route->setDefault('_form', '\Drupal\ghi_content\Form\ArticleWizard');
      $wizard_route->setDefault('node_type', ArticleManager::ARTICLE_BUNDLE);
      $wizard_route->setOption('_node_operation_route', TRUE);
      $collection->add('ghi_content.wizard.article', $wizard_route);

      $wizard_route = clone $route;
      $wizard_route->setPath('node/add/' . DocumentManager::DOCUMENT_BUNDLE);
      $wizard_route->setRequirement('_entity_create_access', 'node:document');
      $wizard_route->setDefault('_form', '\Drupal\ghi_content\Form\DocumentWizard');
      $wizard_route->setDefault('node_type', DocumentManager::DOCUMENT_BUNDLE);
      $wizard_route->setOption('_node_operation_route', TRUE);
      $collection->add('ghi_content.wizard.document', $wizard_route);
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
