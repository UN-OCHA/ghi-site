<?php

namespace Drupal\ghi_subpages\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides an event subscriber that alters routes.
 *
 * This is used to remove the custom access requirement for the publishcontent
 * modules publish route, so that it can be used even if the module is
 * configured to not show local tasks.
 *
 * It also adds access checks to generic node creation and deletion routes so
 * standard section-page structure is changed only through authorized actions.
 *
 * @see ghi_subpages_local_tasks_alter()
 * @see ghi_subpages_preprocess_node_add_list()
 *
 * @package Drupal\ghi_subpages
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Remove check for "Publish and unpublish via local task" config.
    // Normally, the publish route only works if that config is set on
    // /admin/config/workflow/publishcontent, but we want to use this route
    // from other place, so we specifically disable the custom access check.
    if ($route = $collection->get('entity.node.publish')) {
      $requirements = $route->getRequirements();
      unset($requirements['_custom_access']);
      $route->setRequirements($requirements);
    }

    // Add a custom access check for node creation.
    // @see ghi_subpages_preprocess_node_add_list().
    if ($route = $collection->get('node.add')) {
      $route->setRequirement('_custom_access', '\Drupal\ghi_subpages\Controller\SubpagesAdminController::nodeCreateAccess');
    }
    // Node access can be bypassed by editorial roles, so enforce the
    // structural permission separately on the generic delete form.
    if ($route = $collection->get('entity.node.delete_form')) {
      $route->setRequirement('_custom_access', '\Drupal\ghi_subpages\Controller\SubpagesAdminController::nodeDeleteAccess');
    }
    foreach (['node.multiple_delete_confirm', 'entity.node.delete_multiple_form'] as $route_name) {
      if ($route = $collection->get($route_name)) {
        $route->setRequirement('_custom_access', '\Drupal\ghi_subpages\Controller\SubpagesAdminController::nodeBulkDeleteAccess');
      }
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
