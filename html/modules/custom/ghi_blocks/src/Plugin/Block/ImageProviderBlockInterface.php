<?php

namespace Drupal\ghi_blocks\Plugin\Block;

/**
 * Interface for blocks that can provide a single image.
 *
 * This is used for example to provide an image for the front page meta tags,
 * where the representative image comes from the link carousel if available.
 */
interface ImageProviderBlockInterface {

  /**
   * Provide an image uri.
   *
   * @return string
   *   A uri string.
   */
  public function provideImageUri();

}
