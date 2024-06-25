<?php

namespace Drupal\ghi_content\Traits;

/**
 * Trait for helping with remote elements.
 */
trait RemoteElementTrait {

  /**
   * Get a remote source instance.
   *
   * @param string $remote_source
   *   The machine name of the remote source.
   *
   * @return \Drupal\ghi_content\RemoteSource\RemoteSourceInterface
   *   The remote source instance.
   */
  private static function getRemoteSourceInstance($remote_source) {
    /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager $remote_source_manager */
    $remote_source_manager = \Drupal::service('plugin.manager.remote_source');
    return $remote_source_manager->createInstance($remote_source);
  }

  /**
   * Get a remote source instance.
   *
   * @return string[]
   *   The remote source options.
   */
  private static function getRemoteSourceOptions() {
    /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager $remote_source_manager */
    $remote_source_manager = \Drupal::service('plugin.manager.remote_source');
    $definitions = $remote_source_manager->getDefinitions();
    if (empty($definitions)) {
      return [];
    }
    return array_map(function ($item) {
      return $item['label'];
    }, $definitions);
  }

}
