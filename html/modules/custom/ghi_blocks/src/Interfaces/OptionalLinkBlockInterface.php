<?php

namespace Drupal\ghi_blocks\Interfaces;

/**
 * Interface for block plugins that support optional links.
 */
interface OptionalLinkBlockInterface {

  /**
   * Get the configured link if any.
   *
   * @param array $conf
   *   The configuration for the link item.
   *
   * @return \Drupal\Core\Link
   *   The link object.
   */
  public function getLinkFromConfiguration(array $conf);

}
