<?php

namespace Drupal\hpc_downloads\Helpers;

use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPluginInterface;

/**
 * Helper class for downloads.
 */
class DownloadHelper {

  /**
   * Get markup for a download icon.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   The string or markup interface for a download icon.
   */
  public static function getDownloadIconMarkup() {
    return Markup::create('<span class="download-icon"></span>');
  }

  /**
   * Get a render array for a download link using an icon.
   *
   * @param string $uri
   *   A valid URI string.
   *
   * @return mixed[]
   *   A render array for a link.
   */
  public static function getDownloadIcon($uri) {
    return Link::fromTextAndUrl(self::getDownloadIconMarkup(), Url::fromUri($uri))->toRenderable();
  }

  /**
   * Clears stale download files.
   */
  public static function clearDownloadFiles() {
    // Get the lifetime of the download files.
    $threshold = \Drupal::config('hpc_downloads.settings')->get('download_lifetime') * 60;
    $request_time = \Drupal::time()->getRequestTime();

    $delete_stale = function ($uri) use ($threshold, $request_time) {
      if (!file_exists($uri)) {
        return;
      }
      if ($request_time - filemtime($uri) > $threshold) {
        \Drupal::service('file_system')->delete($uri);
        // Remove the record from hpc_download_processes table.
        \Drupal::database()->delete('hpc_download_processes')
          ->condition('file_path', $uri)
          ->execute();
      }
    };
    \Drupal::service('file_system')->scanDirectory(HPCDownloadPluginInterface::DOWNLOAD_DIR, '/.*/', ['callback' => $delete_stale]);

    // Also clear the download database table in case there are stale records,
    // e.g. started but interrupted downloads that don't result in a file being
    // created.
    \Drupal::database()->delete('hpc_download_processes')
      ->condition('started', $request_time - $threshold, '<')
      ->execute();
  }

}
