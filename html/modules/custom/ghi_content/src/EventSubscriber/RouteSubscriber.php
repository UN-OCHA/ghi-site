<?php

namespace Drupal\ghi_content\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\ghi_content\ContentManager\ArticleManager;
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
      // $wizard_route->setOption('parameters', NULL);
      $wizard_route->setPath('node/add/article');
      $wizard_route->setRequirement('_entity_create_access', 'node:article');
      $wizard_route->setDefault('_form', '\Drupal\ghi_content\Form\ArticleWizard');
      $wizard_route->setDefault('node_type', ArticleManager::ARTICLE_BUNDLE);
      $collection->add('ghi_content.wizard.article', $wizard_route);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[RoutingEvents::ALTER] = 'onAlterRoutes';
    return $events;
  }

}
