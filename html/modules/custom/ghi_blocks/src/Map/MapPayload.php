<?php

namespace Drupal\ghi_blocks\Map;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Value object for map data Ajax responses.
 */
final class MapPayload {

  /**
   * The map settings passed to the client-side map initializer.
   *
   * @var array
   */
  private array $map;

  /**
   * The global map configuration settings.
   *
   * @var array
   */
  private array $mapConfig;

  /**
   * The Mapbox configuration settings.
   *
   * @var array
   */
  private array $mapbox;

  /**
   * HTML replacements to run before map initialization.
   *
   * @var array
   */
  private array $html;

  /**
   * Cacheability metadata for the map data response.
   *
   * @var \Drupal\Core\Cache\CacheableMetadata
   */
  private CacheableMetadata $cacheability;

  /**
   * Whether this payload represents an empty map.
   *
   * @var bool
   */
  private bool $empty;

  /**
   * Constructs a lazy map payload.
   *
   * @param array $map
   *   The map settings passed to the client-side map initializer.
   * @param array $map_config
   *   The global map configuration settings.
   * @param array $mapbox
   *   The Mapbox configuration settings.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Cacheability metadata for the map data response.
   * @param array $html
   *   HTML replacements keyed by CSS selector.
   * @param bool $empty
   *   Whether this payload represents an empty map.
   */
  private function __construct(array $map, array $map_config, array $mapbox, CacheableMetadata $cacheability, array $html = [], bool $empty = FALSE) {
    $this->map = $map;
    $this->mapConfig = $map_config;
    $this->mapbox = $mapbox;
    $this->html = $html;
    $this->cacheability = self::normalizeCacheability($cacheability);
    $this->empty = $empty;
  }

  /**
   * Creates a payload for a map with data.
   *
   * @param array $map
   *   The map settings passed to the client-side map initializer.
   * @param array $map_config
   *   The global map configuration settings.
   * @param array $mapbox
   *   The Mapbox configuration settings.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Cacheability metadata for the lazy map response.
   * @param array $html
   *   HTML replacements keyed by CSS selector.
   *
   * @return self
   *   The map payload.
   */
  public static function forMap(array $map, array $map_config, array $mapbox, CacheableMetadata $cacheability, array $html = []): self {
    return new self($map, $map_config, $mapbox, $cacheability, $html);
  }

  /**
   * Creates a payload for an empty map.
   *
   * @param array $map
   *   Minimal map settings identifying the map and its settings key.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Cacheability metadata for the lazy map response.
   *
   * @return self
   *   The lazy map payload.
   */
  public static function forEmptyMap(array $map, CacheableMetadata $cacheability): self {
    return new self($map, [], [], $cacheability, [], TRUE);
  }

  /**
   * Creates cacheability metadata from cache tags.
   *
   * @param string[] $cache_tags
   *   Cache tags for the map data response.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   The cacheability metadata.
   */
  public static function cacheabilityFromTags(array $cache_tags): CacheableMetadata {
    return (new CacheableMetadata())->setCacheTags($cache_tags);
  }

  /**
   * Whether this payload represents an empty map.
   *
   * @return bool
   *   TRUE if the map has no data, FALSE otherwise.
   */
  public function isEmpty(): bool {
    return $this->empty;
  }

  /**
   * Gets the map settings.
   *
   * @return array
   *   The map settings.
   */
  public function getMap(): array {
    return $this->map;
  }

  /**
   * Gets HTML replacements.
   *
   * @return array
   *   HTML replacements keyed by CSS selector.
   */
  public function getHtml(): array {
    return $this->html;
  }

  /**
   * Gets the response attachments for a non-empty map.
   *
   * @return array
   *   The response attachments.
   */
  public function getAttachments(): array {
    if ($this->isEmpty()) {
      return [];
    }
    return [
      'library' => [
        'ghi_blocks/map.gl',
      ],
      'drupalSettings' => [
        'map_config' => $this->mapConfig,
        'mapbox' => $this->mapbox,
      ],
    ];
  }

  /**
   * Gets cacheability metadata for the map data response.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   The cacheability metadata.
   */
  public function getCacheability(): CacheableMetadata {
    return $this->cacheability;
  }

  /**
   * Converts the payload to the previous array shape.
   *
   * @return array
   *   The array representation of the payload.
   */
  public function toArray(): array {
    return [
      'empty' => $this->isEmpty(),
      'map' => $this->map,
      'html' => $this->html,
      'map_config' => $this->mapConfig,
      'mapbox' => $this->mapbox,
      'cache_tags' => $this->cacheability->getCacheTags(),
    ];
  }

  /**
   * Adds the common map data response cache metadata.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Cacheability metadata supplied by the map block.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   The normalized cacheability metadata.
   */
  private static function normalizeCacheability(CacheableMetadata $cacheability): CacheableMetadata {
    $default_cacheability = (new CacheableMetadata())
      ->setCacheMaxAge(60)
      ->setCacheContexts([
        'url.query_args',
      ]);
    return $cacheability->merge($default_cacheability);
  }

}
