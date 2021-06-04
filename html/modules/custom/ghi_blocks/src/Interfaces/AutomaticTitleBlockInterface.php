<?php

namespace Drupal\ghi_blocks\Interfaces;

/**
 * Interface for blocks having automatic titles.
 */
interface AutomaticTitleBlockInterface {

  /**
   * Get a title for a block instance.
   *
   * @return string|null
   *   The title string or NULL if none is available.
   */
  public function getAutomaticBlockTitle();

}
