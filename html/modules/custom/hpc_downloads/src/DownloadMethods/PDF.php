<?php

namespace Drupal\hpc_downloads\DownloadMethods;

use Drupal\Core\Url;

use Drupal\hpc_downloads\Helpers\FileHelper;
use Drupal\hpc_downloads\DownloadRecord;

/**
 * Create PDF documents.
 */
class PDF {

  /**
   * Create the PDF/PNG download.
   */
  public static function createDownloadFile($record, $options) {
    $url = Url::fromUserInput($options['uri'])->setAbsolute()->toString();
    $options['url'] = $url;
    $snap_params = self::prepareOptions($options);
    return self::writeFile($record, $options, $snap_params);
  }

  /**
   * Call snap service to generate document.
   */
  private static function generateDocument($url, $options) {
    try {
      $response = ocha_snap_generate($url, $options);
      return $response;
    }
    catch (\Exception $e) {
      \Drupal::logger('hpc_downloads')->error('Snap: ' . $e->getMessage());
      return NULL;
    }
  }

  /**
   * Download the document.
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
    $response = self::generateDocument($options['url'], $snap_params);
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

  /**
   * Prepare default parameters.
   */
  private static function prepareOptions($options) {
    // Prepare params to be passed to Snap Service.
    $query_params = array_filter([
      'url' => $options['url'],
      'output' => 'pdf',
      'pdfFormat' => 'A2',
      'cookies' => json_encode(\Drupal::requestStack()->getCurrentRequest()->cookies),
      'delay' => 2000,
      'pdfMarginTop' => 2,
      'pdfMarginBottom' => 2,
      'pdfMarginUnit' => 'cm',
    ]);
    return $query_params;
  }

}
