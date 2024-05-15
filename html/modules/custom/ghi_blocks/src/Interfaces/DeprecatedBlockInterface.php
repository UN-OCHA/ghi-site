<?php

namespace Drupal\ghi_blocks\Interfaces;

/**
 * Defines an interface for deprecated blocks that can be replaced with others.
 */
interface DeprecatedBlockInterface {

  /**
   * Return the block config defintion for the element that replaces this.
   *
   * @return array|null
   *   A configuration array that describes the block plugin which replaces
   *   the deprecated plugin. Or NULL if the deprecated block should not be
   *   replaced.
   */
  public function getBlockConfigForReplacement();

}
