<?php

namespace Drupal\ghi_base_objects\Plugin\Menu\LocalAction;

use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\Entity\BaseObjectType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a local action plugin with a dynamic title.
 */
class BaseObjectsUpdateLocalAction extends LocalActionDefault {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getTitle(Request $request = NULL) {
    $page_title = NULL;
    if ($route = $request->attributes->get(RouteObjectInterface::ROUTE_OBJECT)) {
      $page_title = \Drupal::service('title_resolver')->getTitle($request, $route);
    }
    return new TranslatableMarkup('Update @base_object_type', [
      '@base_object_type' => $page_title ? strtolower($page_title) : $this->t('base objects'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters(RouteMatchInterface $route_match) {
    $route_parameters = parent::getRouteParameters($route_match);
    $route_name = $route_match->getRouteName();
    if (strpos($route_name, 'view.base_objects.') === 0) {
      [,, $base_object_type_id] = explode('.', $route_name);
      if (BaseObjectType::load($base_object_type_id)) {
        $route_parameters['base_object_type'] = $base_object_type_id;
      }
    }
    return $route_parameters;
  }

}
