<?php

namespace Drupal\ghi_blocks\Interfaces;

/**
 * Interface for block plugins that support custom links.
 */
interface CustomLinkBlockInterface {

  /**
   * Get the configured link if any.
   *
   * @param array $conf
   *   The configuration for the link item.
   * @param array $contexts
   *   The contexts for the link creation.
   *
   * @return \Drupal\Core\Link
   *   The link object.
   */
  public function getLinkFromConfiguration(array $conf, array $contexts);

}
