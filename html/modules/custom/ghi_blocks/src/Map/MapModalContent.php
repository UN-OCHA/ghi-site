<?php

namespace Drupal\ghi_blocks\Map;

/**
 * Helpers for modal content embedded in map settings.
 */
final class MapModalContent {

  /**
   * The expirable key/value collection for lazy preview modal content.
   */
  public const CONFIGURATION_PREVIEW_COLLECTION = 'ghi_blocks.map_preview_modal';

  /**
   * The lifetime of stored lazy preview modal content.
   */
  public const CONFIGURATION_PREVIEW_TTL = 3600;

  /**
   * The fallback data index for maps that do not have tabs.
   */
  public const DEFAULT_DATA_INDEX = 'default';

  /**
   * The fallback variant id for maps that do not use variants.
   */
  public const DEFAULT_VARIANT_ID = 'base';

  /**
   * Extract modal contents from a map settings array.
   *
   * This removes the modal content from the map settings array passed by
   * reference and returns the extracted modal data grouped by map data index
   * and variant id.
   *
   * @param array $map
   *   The map settings array.
   *
   * @return array
   *   The extracted modal data entries.
   */
  public static function extractFromMap(array &$map): array {
    if (empty($map['json']) || !is_array($map['json'])) {
      return [];
    }

    $entries = [];
    if (self::isDataset($map['json'])) {
      self::extractFromDataset($map['json'], self::DEFAULT_DATA_INDEX, $entries);
      return $entries;
    }

    foreach ($map['json'] as $data_index => &$dataset) {
      if (!is_array($dataset)) {
        continue;
      }
      self::extractFromDataset($dataset, (string) $data_index, $entries);
    }
    unset($dataset);

    return $entries;
  }

  /**
   * Build a store key for a modal content entry.
   *
   * @param string $token
   *   The modal data token.
   * @param string $data_index
   *   The map data index.
   * @param string $variant_id
   *   The map variant id.
   *
   * @return string
   *   The key/value store key.
   */
  public static function buildStoreKey(string $token, string $data_index, string $variant_id): string {
    return implode(':', [$token, $data_index, $variant_id]);
  }

  /**
   * Check whether an array is a map data set.
   *
   * @param array $data
   *   The candidate map data.
   *
   * @return bool
   *   TRUE if the data looks like a map data set.
   */
  private static function isDataset(array $data): bool {
    return array_key_exists('locations', $data) || array_key_exists('modal_contents', $data) || array_key_exists('variants', $data);
  }

  /**
   * Extract modal content from one map data set.
   *
   * @param array $dataset
   *   The map data set.
   * @param string $data_index
   *   The map data index.
   * @param array $entries
   *   The extracted modal content entries.
   */
  private static function extractFromDataset(array &$dataset, string $data_index, array &$entries): void {
    if (isset($dataset['modal_contents']) && is_array($dataset['modal_contents'])) {
      $entries[] = [
        'data_index' => $data_index,
        'variant_id' => self::DEFAULT_VARIANT_ID,
        'modal_contents' => $dataset['modal_contents'],
      ];
    }
    unset($dataset['modal_contents']);

    if (empty($dataset['variants']) || !is_array($dataset['variants'])) {
      return;
    }

    foreach ($dataset['variants'] as $variant_id => &$variant) {
      if (!is_array($variant)) {
        continue;
      }
      if (isset($variant['modal_contents']) && is_array($variant['modal_contents'])) {
        $entries[] = [
          'data_index' => $data_index,
          'variant_id' => (string) $variant_id,
          'modal_contents' => $variant['modal_contents'],
        ];
      }
      unset($variant['modal_contents']);
    }
    unset($variant);
  }

}
