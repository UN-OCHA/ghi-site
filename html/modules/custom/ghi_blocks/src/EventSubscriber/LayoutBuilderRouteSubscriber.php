<?php

namespace Drupal\ghi_blocks\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides an event subscriber that alters routes.
 *
 * This is used to replace the generic "Configure block" title in layout
 * builder modal windows with the admin label of the plugin that is
 * added/updated.
 *
 * @package Drupal\ghi_blocks
 */
class LayoutBuilderRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $title_callback_add = '\Drupal\ghi_blocks\Controller\LayoutBuilderBlockController::getAddBlockFormTitle';
    $title_callback_update = '\Drupal\ghi_blocks\Controller\LayoutBuilderBlockController::getUpdateBlockFormTitle';

    if ($route = $collection->get('layout_builder.add_block')) {
      $route->setDefault('_title_callback', $title_callback_add);
    }

    if ($route = $collection->get('layout_builder.update_block')) {
      $route->setDefault('_title_callback', $title_callback_update);
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
