<?php

namespace Drupal\hpc_api\Traits;

/**
 * Provide a simple in-memory cache.
 */
trait SimpleCacheTrait {

  /**
   * Build a cache key from an associative array.
   *
   * @param array $array
   *   The input array.
   *
   * @return string
   *   A cache key string.
   */
  public static function getCacheKey(array $array) {
    ksort($array);
    array_unshift($array, [
      'class' => get_called_class(),
      'method' => debug_backtrace(!DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'],
    ]);
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
    $cache_store = &drupal_static(__FUNCTION__, []);

    if ($data === NULL && $reset === TRUE) {
      // Clear the cached data as requested.
      unset($cache_store[$cache_key]);
      self::cacheBackend()->delete($cache_key);
      return NULL;
    }
    elseif ($data === NULL) {
      // Retrieve data from static cache.
      if (array_key_exists($cache_key, $cache_store)) {
        return $cache_store[$cache_key];
      }
      $cache = self::cacheBackend()->get($cache_key);
      return $cache ? $cache->data : NULL;
    }

    // Store data in the cache.
    $cache_store[$cache_key] = $data;
    self::cacheBackend()->set($cache_key, $data);
    return $cache_store[$cache_key];
  }

  /**
   * Get the cache backend.
   *
   * @return \Drupal\Core\Cache\CacheBackendInterface
   *   A cache object.
   */
  private static function cacheBackend() {
    return \Drupal::cache();
  }

}
