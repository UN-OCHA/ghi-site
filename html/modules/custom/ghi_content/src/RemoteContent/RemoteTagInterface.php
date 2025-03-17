<?php

namespace Drupal\ghi_content\RemoteContent;

/**
 * Interface class for remote tags.
 */
interface RemoteTagInterface {

  /**
   * Get the type string of the tag.
   *
   * @return string
   *   The tag type.
   */
  public function getType();

}
