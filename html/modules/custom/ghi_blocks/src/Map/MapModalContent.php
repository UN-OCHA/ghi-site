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
    $modal_contents = self::extractModalContents($dataset);
    if (!empty($modal_contents)) {
      $entries[] = [
        'data_index' => $data_index,
        'variant_id' => self::DEFAULT_VARIANT_ID,
        'modal_contents' => $modal_contents,
      ];
    }

    if (empty($dataset['variants']) || !is_array($dataset['variants'])) {
      // Compact object-filter variants are not full map variants yet, but their
      // modal content still needs to be moved behind the preview lazy endpoint.
      self::extractFromObjectFilterVariants($dataset, $data_index, $entries);
      return;
    }

    foreach ($dataset['variants'] as $variant_id => &$variant) {
      if (!is_array($variant)) {
        continue;
      }
      $modal_contents = self::extractModalContents($variant);
      if (!empty($modal_contents)) {
        $entries[] = [
          'data_index' => $data_index,
          'variant_id' => (string) $variant_id,
          'modal_contents' => $modal_contents,
        ];
      }
    }
    unset($variant);
    // Some maps ship object filters separately from normal variants to avoid
    // duplicating full location payloads. Strip their modal HTML as well so
    // preview responses stay small regardless of variant shape.
    self::extractFromObjectFilterVariants($dataset, $data_index, $entries);
  }

  /**
   * Extract modal content from compact object-filter variants.
   *
   * @param array $dataset
   *   The map data set.
   * @param string $data_index
   *   The map data index.
   * @param array $entries
   *   The extracted modal content entries.
   */
  private static function extractFromObjectFilterVariants(array &$dataset, string $data_index, array &$entries): void {
    if (empty($dataset['object_filter_variants']) || !is_array($dataset['object_filter_variants'])) {
      return;
    }

    foreach ($dataset['object_filter_variants'] as $variant_id => &$variant) {
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

  /**
   * Extract modal content from a data set or variant.
   *
   * @param array $dataset
   *   The map data set or variant.
   *
   * @return array
   *   The modal content keyed by object id.
   */
  private static function extractModalContents(array &$dataset): array {
    $modal_contents = isset($dataset['modal_contents']) && is_array($dataset['modal_contents']) ? $dataset['modal_contents'] : [];
    unset($dataset['modal_contents']);

    if (empty($dataset['locations']) || !is_array($dataset['locations'])) {
      return $modal_contents;
    }

    foreach ($dataset['locations'] as &$location) {
      if (!is_array($location)) {
        continue;
      }
      $object_id = $location['object_id'] ?? $location['id'] ?? NULL;
      if ($object_id !== NULL && isset($location['modal_contents']) && is_array($location['modal_contents'])) {
        $modal_contents[(string) $object_id] = $location['modal_contents'];
      }
      unset($location['modal_contents']);
    }
    unset($location);

    return $modal_contents;
  }

}
