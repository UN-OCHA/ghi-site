<?php

namespace Drupal\ghi_blocks\Helpers;

use Drupal\ghi_form_elements\Traits\CustomLinkTrait;

/**
 * Helper function for urls.
 *
 * @see \Drupal\ghi_form_elements\Traits\CustomLinkTrait
 */
class UrlHelper {

  use CustomLinkTrait;

  /**
   * Transform the given url to an entity uri if possible.
   *
   * @param string $url
   *   The url to transform.
   * @param string $host
   *   Optional: The host url.
   *
   * @return string
   *   The transformed string.
   */
  public static function transformUrlToEntityUri($url, $host = NULL) {
    return self::transformUrl($url, $host);
  }

}
