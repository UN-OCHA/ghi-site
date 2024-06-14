<?php

namespace Drupal\ghi_subpages\Entity;

use Drupal\node\NodeInterface;

/**
 * Interface for subpage nodes.
 */
interface SubpageNodeInterface extends NodeInterface {

  /**
   * Get the parent node.
   *
   * @return \Drupal\node\NodeInterface
   *   The parent node.
   */
  public function getParentNode();

  /**
   * Get the parent section node.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface
   *   The parent section node.
   */
  public function getParentBaseNode();

}
