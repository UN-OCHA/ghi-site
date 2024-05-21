<?php

namespace Drupal\ghi_sections\Traits;

use Drupal\Core\Url;
use Drupal\ghi_sections\Entity\SectionNodeInterface;

/**
 * Trait for handling section paths.
 */
trait SectionPathTrait {

  /**
   * Get the section node from the path.
   *
   * This processes the path recursively until it can find an alias that
   * represents a section node.
   *
   * @param string $path
   *   The path to process.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface|null
   *   The section node or NULL if not found.
   */
  private function getSectionNodeFromPath($path = NULL) {
    $path = $path ?? \Drupal::requestStack()->getCurrentRequest()->getPathInfo();
    $section = NULL;
    $path_internal = \Drupal::service('path_alias.manager')->getPathByAlias($path);
    try {
      $params = Url::fromUri("internal:" . $path_internal)->getRouteParameters();
      $entity_type = key($params);
      $loaded = \Drupal::entityTypeManager()->getStorage($entity_type)->load($params[$entity_type]);
      $section = $loaded instanceof SectionNodeInterface ? $loaded : NULL;
    }
    catch (\Exception $e) {
      // Just catch any issue and pretend this didn't happen.
    }
    if (!$section && strrpos($path, '/') > 1) {
      $path = substr($path, 0, strrpos($path, '/'));
      $section = $this->getSectionNodeFromPath($path);
    }
    return $section instanceof SectionNodeInterface ? $section : NULL;
  }

}
