<?php

namespace Drupal\ghi_subpages\Entity;

/**
 * Interface for subpage nodes using icons.
 */
interface SubpageIconInterface extends SubpageNodeInterface {

  /**
   * Get the markup for the icon.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The markup of the icon.
   */
  public function getIcon();

}
