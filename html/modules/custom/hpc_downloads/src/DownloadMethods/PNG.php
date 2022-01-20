<?php

namespace Drupal\hpc_downloads\DownloadMethods;

use Drupal\Core\Url;

use Drupal\hpc_downloads\Helpers\FileHelper;
use Drupal\hpc_downloads\DownloadRecord;

/**
 * Create PNG files.
 */
class PNG {

  /**
   * Create the PDF/PNG download.
   */
  public static function createDownloadFile($record, $options) {
    $snap_params = self::prepareOptions($options);
    return self::writeFile($record, $options, $snap_params);
  }

  /**
   * Call snap service to generate document.
   */
  private static function generateDocument($url, $options) {
    return ocha_snap($url, $options);
  }

  /**
   * Prepare default parameters.
   */
  private static function prepareOptions($options) {
    // Prepare params to be passed to Snap Service.
    $query_params = [
      'output' => 'png',
      'logo' => 'fts',
      'selector' => '.panel-pane.block-' . $options['block_uuid'],
      'width' => 1280,
    ];

    return $query_params;
  }

  /**
   * Write the download file.
   */
  private static function writeFile($record, $options, $snap_params) {
    $record['file_path'] = $record['options']['file_path'];
    // Check directory is writable.
    if (!is_writable(dirname($record['file_path']))) {
      \Drupal::logger('hpc_downloads')->error('File system is not writable.');
      DownloadRecord::closeRecord($record, DownloadRecord::STATUS_ERROR);
      return FALSE;
    }

    // Create a temporary file, so that downloads that might be going on will
    // not be interfered with during the possibly long running file generation.
    $temp_name = 'temporary://' . $record['options']['file_name'] . '.' . $options['type'];

    // Get response from the snap service.
    $url = Url::fromUserInput($options['uri'])->setAbsolute()->toString();
    $response = self::generateDocument($url, $snap_params);
    if (!$response) {
      \Drupal::logger('hpc_downloads')->error('Error response from snap service.');
      DownloadRecord::closeRecord($record, DownloadRecord::STATUS_ERROR);
      FileHelper::deleteFile($temp_name);
      return FALSE;
    }
    FileHelper::saveData($response, $temp_name);

    // Move temporary file to the final destination.
    $file_path_final = FileHelper::moveFile($temp_name, $record['file_path']);
    if ($file_path_final === FALSE) {
      if (is_file($file_path_final) && is_file($temp_name)) {
        \Drupal::logger('hpc_downloads')->error('Failed to delete temporary download file.');
      }
      \Drupal::logger('hpc_downloads')->error('Failed to move temporary download file from temporary directory to the downloads directory.');
      return FALSE;
    }

    $record['file_path'] = $file_path_final;
    DownloadRecord::updateRecord($record);
    // All done. now close the record with success status.
    DownloadRecord::closeRecord($record);
    return TRUE;
  }

}
