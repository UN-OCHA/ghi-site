<?php

namespace Drupal\common_design_subtheme;

/**
 * Class for handling block settings.
 */
class BlockSettings {

  /**
   * Get the settings for a block.
   *
   * @param string $block_id
   *   The block id.
   * @param string $property
   *   Optional argument to retrieve a specific property of the block settings.
   *
   * @return object|null
   *   Either the block settings as an object or NULL.
   */
  public static function getBlockSettings(string $block_id, ?string $property = NULL) {
    $block_settings = \Drupal::request()->query->get('bs');
    $block_settings = (array) json_decode($block_settings ? base64_decode($block_settings) : '{}');
    $block_settings = $block_settings[$block_id] ?? NULL;
    if (!$block_settings) {
      return NULL;
    }
    return $property !== NULL ? (property_exists($block_settings, $property) ? $block_settings->{$property} : NULL) : $block_settings;
  }

}
