<?php

namespace Drupal\hpc_downloads\DownloadMethods;

use Drupal\Core\Url;

use Drupal\hpc_common\Helpers\ThemeHelper;
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
      $response = ocha_snap($url, $options);
      return $response;
    }
    catch (\Exception $e) {
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
    // Prepare header and footer for Snap Service PDF.
    $pdf_header = ThemeHelper::theme('hpc_download_pdf_header', [
      '#title' => $options['title'],
      '#caption' => $options['caption'],
      '#link' => $options['url'],
      '#date' => date('d-M-Y'),
    ], TRUE, FALSE);

    // Prepare params to be passed to Snap Service.
    $query_params = [
      'url' => $options['url'],
      'output' => 'pdf',
      'logo' => 'fts',
      'pdfHeader' => $pdf_header,
    ];

    // Set the pdfMarginTop based on header region length.
    // This is no sure shot formula to calculate the value of pdfMarginTop and
    // is all based on trial and error.
    $header_length = strlen($options['caption']) + strlen($options['url']);
    $query_params['pdfMarginTop'] = $header_length < 120 ? '160' : ($header_length < 200 ? '200' : '230');

    if (!isset($options['exclude_pdf_footer'])) {
      $pdf_footer = ThemeHelper::theme('hpc_download_pdf_footer', [
        '#footer_text' => t('Compiled by OCHA on the basis of reports from donor and recipient organizations.'),
      ], TRUE, FALSE);
      $query_params += [
        'pdfFooter' => $pdf_footer,
      ];
    }

    return $query_params;
  }

}
