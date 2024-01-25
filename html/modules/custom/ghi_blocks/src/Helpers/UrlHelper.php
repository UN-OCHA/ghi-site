<?php

namespace Drupal\ghi_blocks\Helpers;

use Drupal\ghi_form_elements\Traits\OptionalLinkTrait;

/**
 * Helper function for urls.
 *
 * @see \Drupal\ghi_form_elements\Traits\OptionalLinkTrait
 */
class UrlHelper {

  use OptionalLinkTrait;

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
