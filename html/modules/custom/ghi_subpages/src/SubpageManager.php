<?php

namespace Drupal\ghi_subpages;

use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_subpages\Helpers\SubpageHelper;

/**
 * Subpage manager service class.
 */
class SubpageManager extends BaseObjectSubpageManager {

  /**
   * Load a subpage node for the given base object.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object for which to load a dedicated subpage.
   *
   * @return \Drupal\node\NodeInterface|null
   *   A node object or NULL.
   */
  public function loadSubpageForBaseObject(BaseObjectInterface $base_object) {
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => SubpageHelper::getSubpageTypes(),
      'field_base_object' => $base_object->id(),
    ]);
    return count($nodes) == 1 ? reset($nodes) : NULL;
  }

}
