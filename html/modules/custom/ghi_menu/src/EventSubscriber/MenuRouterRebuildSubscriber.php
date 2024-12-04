<?php

namespace Drupal\ghi_menu\EventSubscriber;

use Drupal\Core\Routing\RoutingEvents;
use Drupal\ghi_menu\GhiMenuStorageHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Rebuilds the default menu links and runs menu-specific code if necessary.
 */
class MenuRouterRebuildSubscriber implements EventSubscriberInterface {

  /**
   * The menu storage helper service.
   *
   * @var \Drupal\ghi_menu\GhiMenuStorageHelper
   */
  protected $menuStorageHelper;

  /**
   * Constructs the MenuRouterRebuildSubscriber object.
   *
   * @param \Drupal\ghi_menu\GhiMenuStorageHelper $menu_storage_helper
   *   The menu storage helper service.
   */
  public function __construct(GhiMenuStorageHelper $menu_storage_helper) {
    $this->menuStorageHelper = $menu_storage_helper;
  }

  /**
   * Cleanup the menu tree storage.
   *
   * @param \Drupal\Component\EventDispatcher\Event $event
   *   The event object.
   */
  public function onRouterRebuild($event) {
    $this->menuStorageHelper->cleanupMenuStorage();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run after CachedRouteRebuildSubscriber.
    $events[RoutingEvents::FINISHED][] = ['onRouterRebuild', 120];
    return $events;
  }

}
