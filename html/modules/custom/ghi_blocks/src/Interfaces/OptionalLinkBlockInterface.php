<?php

namespace Drupal\ghi_blocks\Interfaces;

/**
 * Interface for block plugins that support optional links.
 */
interface OptionalLinkBlockInterface {

  /**
   * Check if the block has an additional link configured.
   *
   * @return bool
   *   TRUE if there is a link to be displayed, FALSE otherwise.
   */
  public function hasLink();

  /**
   * Get the configured link if any.
   *
   * @return \Drupal\Core\Link
   *   The link object.
   */
  public function getLink();

}
