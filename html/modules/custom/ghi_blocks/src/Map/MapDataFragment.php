<?php

namespace Drupal\ghi_blocks\Map;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Value object for one independently cacheable lazy map JSON response.
 *
 * "Fragment" is the HTTP/cacheability term used by the controller layer. The
 * wrapped data may be a map data slice, such as the locations for one
 * tab/variant, or it may be one location's sidebar/modal payload. Keeping the
 * wrapper generic lets both endpoints share the same cacheability handling
 * without implying that both fragments contain the same shape of data.
 */
final class MapDataFragment {

  /**
   * The JSON-serializable response body for this fragment.
   *
   * @var array
   */
  private array $data;

  /**
   * Cacheability metadata for the fragment response.
   *
   * @var \Drupal\Core\Cache\CacheableMetadata
   */
  private CacheableMetadata $cacheability;

  /**
   * Constructs a map data fragment.
   *
   * @param array $data
   *   The JSON-serializable fragment data.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Cacheability metadata for the fragment response.
   */
  public function __construct(array $data, CacheableMetadata $cacheability) {
    $this->data = $data;
    $this->cacheability = self::normalizeCacheability($cacheability);
  }

  /**
   * Gets the fragment data.
   *
   * @return array
   *   The JSON-serializable fragment data.
   */
  public function getData(): array {
    return $this->data;
  }

  /**
   * Gets cacheability metadata for the fragment response.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   The cacheability metadata.
   */
  public function getCacheability(): CacheableMetadata {
    return $this->cacheability;
  }

  /**
   * Adds common fragment response cache metadata.
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

    return $default_cacheability->merge($cacheability);
  }

}
