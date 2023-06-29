<?php

namespace Drupal\ghi_sections\Entity;

use Drupal\node\NodeInterface;

/**
 * Interface for image nodes.
 */
interface ImageNodeInterface extends NodeInterface {

  /**
   * Get the image field for the node.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   The field item list, containing the image field items.
   */
  public function getImage();

}
