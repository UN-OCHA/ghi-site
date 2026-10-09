<?php

namespace Drupal\ghi_blocks\Interfaces;

/**
 * Defines a deprecated block that can migrate to a replacement plugin.
 */
interface DeprecatedBlockReplacementInterface extends DeprecatedBlockInterface {

  /**
   * Returns the configuration for the replacement block plugin.
   *
   * @return array|null
   *   The replacement block configuration, or NULL to leave the block intact.
   */
  public function getBlockConfigForReplacement(): ?array;

}
