<?php

namespace Drupal\ghi_sections\Entity;

use Drupal\node\NodeInterface;

/**
 * Interface for section nodes.
 */
interface SectionNodeInterface extends NodeInterface {

  /**
   * Get the page title.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The markup object for the titel.
   */
  public function getPageTitle();

  /**
   * Get the page subtitle.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The markup object for the subtitel.
   */
  public function getPageSubTitle();

  /**
   * Get the image field for the node.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   The field item list, containing the image field items.
   */
  public function getImage();

}
