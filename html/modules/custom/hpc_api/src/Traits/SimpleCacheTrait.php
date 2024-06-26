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
    // First sort the incoming arguments.
    ksort($array);
    // Then get information about the caller.
    $called_class = array_key_last(array_flip(explode('\\', get_called_class())));
    $called_method = debug_backtrace(!DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
    $caller = $called_class . ':' . $called_method;
    // And finally, turn the array into a string, clean that up and encode to
    // limit character size.
    $cache_key = $caller . ':' . sha1(urldecode(http_build_query($array)));
    return $cache_key;
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
   * @param bool $cache_base_time
   *   The base time to use for the cache. If set, cached data created before
   *   will not be used. This can be used for example in data migrations, where
   *   we want to use cache, but we also want to be sure that the actual import
   *   data is up to date. Instead of clearing the cache for the whole site, we
   *   can simply use the start time of the migration as the base time, and
   *   only retrieve data once for every API call made during the data migration
   *   process.
   * @param array $cache_tags
   *   The cache tags to associate with this cache entry. Optional and only
   *   relevant when storing data.
   *
   * @return mixed|void
   *   Either the stored data or nothing.
   */
  public function cache($cache_key, $data = NULL, $reset = FALSE, $cache_base_time = NULL, $cache_tags = []) {
    $cache_store = &drupal_static(get_called_class() . '::' . __FUNCTION__, []);

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
      if (!$cache || $cache_base_time && $cache_base_time > $cache->created) {
        // Cache is too old, so we renew.
        return NULL;

      }
      return $cache->data;
    }

    // Store data in the cache.
    $cache_store[$cache_key] = $data;
    $expiration_time = self::getRequestTime() + self::getCacheLifetime();
    self::cacheBackend()->set($cache_key, $data, $expiration_time, $cache_tags);
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

  /**
   * Get the cache lifetime.
   *
   * @return int
   *   The cache lifetime in seconds.
   */
  private static function getCacheLifetime() {
    return \Drupal::config('hpc_api.settings')->get('cache_lifetime');
  }

  /**
   * Get the time of the request.
   *
   * @return int
   *   The unix timestamp of the request.
   */
  private static function getRequestTime() {
    return \Drupal::time()->getRequestTime();
  }

}
