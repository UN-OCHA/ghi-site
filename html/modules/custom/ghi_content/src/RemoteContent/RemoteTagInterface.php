<?php

namespace Drupal\ghi_content\RemoteContent;

/**
 * Interface class for remote tags.
 */
interface RemoteTagInterface {

  /**
   * Get the type string of the tag.
   *
   * @return string|null
   *   The tag type or NULL.
   */
  public function getType();

}
