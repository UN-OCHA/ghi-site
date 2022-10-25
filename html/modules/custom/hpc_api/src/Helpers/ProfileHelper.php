<?php

namespace Drupal\hpc_api\Helpers;

/**
 * Helper class for profiling runtime code.
 */
class ProfileHelper {

  const STATE_START = 'start';
  const STATE_END = 'end';

  /**
   * Start a profile.
   *
   * @param string $key
   *   The key to identify a specific profile item.
   *
   * @return string
   *   The potentially altered key that identifies this profile.
   */
  public static function profileStart($key = NULL) {
    return self::profile($key, self::STATE_START);
  }

  /**
   * Start a profile.
   *
   * @param string $key
   *   The key to identify a specific profile item.
   */
  public static function profileEnd($key = NULL) {
    self::profile($key, self::STATE_END);
  }

  /**
   * Get a profile summary.
   *
   * @return array
   *   An array with one item per started profile.
   */
  public static function profileSummary() {
    return self::profile();
  }

  /**
   * Profile the given key.
   *
   * @param string $key
   *   The key to identify a specific profile item.
   * @param string $state
   *   Either ProfileHelper::STATE_START or ProfileHelper::STATE_END.
   */
  private static function profile($key = NULL, $state = NULL) {
    $profile = &drupal_static(__FUNCTION__, []);
    if ($key === NULL) {
      return $profile;
    }
    if ($state === NULL) {
      // Invalid request.
      return NULL;
    }

    if (array_key_exists($key, $profile)) {
      if ($state == self::STATE_START) {
        $index = 0;
        while (array_key_exists($key . '_' . $index, $profile)) {
          $index++;
        }
        $key = $key . '_' . $index;
      }
    }
    if ($state === self::STATE_START) {
      $profile[$key] = [
        'state' => $state,
        'duration' => NULL,
        'memory_usage' => NULL,
        'time' => [
          'start' => microtime(TRUE),
          'end' => NULL,
        ],
        'memory' => [
          'start' => memory_get_usage(),
          'end' => NULL,
        ],
      ];
      return $key;
    }
    if ($state === self::STATE_END && array_key_exists($key, $profile) && $profile[$key]['state'] == self::STATE_START) {
      $profile[$key]['state'] = $state;
      $profile[$key]['time']['end'] = microtime(TRUE);
      $profile[$key]['memory']['end'] = memory_get_usage();
      $profile[$key]['duration'] = $profile[$key]['time']['end'] - $profile[$key]['time']['start'];
      $profile[$key]['memory_usage'] = $profile[$key]['memory']['end'] - $profile[$key]['memory']['start'];
    }
  }

}
