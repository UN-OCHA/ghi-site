<?php

namespace Drupal\hpc_api\Traits;

/**
 * Provide a simple in-memory cache.
 */
trait SimpleCacheTrait {

  /**
   * Build a cache from an associative array.
   *
   * @param array $array
   *   The input array.
   *
   * @return string
   *   A cache key string.
   */
  public function getCacheKeyFromAssociativeArray(array $array) {
    ksort($array);
    return http_build_query($array);
  }

  /**
   * Custom in-memory static cache storage.
   *
   * @param string $cache_key
   *   The cache key.
   * @param mixed $data
   *   The data to store.
   * @param bool $reset
   *   Whether to reset the cache.
   *
   * @return mixed|void
   *   Either the stored data or nothing.
   */
  public function cache($cache_key, $data = NULL, $reset = FALSE) {
    $cache_store = &drupal_static(__FUNCTION__);

    if ($data === NULL && $reset === TRUE) {
      // Clear the cached data as requested.
      unset($cache_store[$cache_key]);
      return NULL;
    }
    elseif ($data === NULL) {
      // Retrieve data from static cache.
      if (isset($cache_store[$cache_key])) {
        return $cache_store[$cache_key];
      }
      return NULL;
    }

    // Also store it in the static cache.
    $cache_store[$cache_key] = $data;
  }

}
