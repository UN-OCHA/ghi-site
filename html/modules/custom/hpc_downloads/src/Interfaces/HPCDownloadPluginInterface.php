<?php

namespace Drupal\hpc_downloads\Interfaces;

use Drupal\hpc_common\Plugin\HPCPluginInterface;

/**
 * Interface declaration for generic HPC downloads.
 */
interface HPCDownloadPluginInterface extends HPCPluginInterface {

  const DOWNLOAD_TYPE_PDF = 'pdf';
  const DOWNLOAD_TYPE_PNG = 'png';
  const DOWNLOAD_TYPE_XLS = 'xls';
  const DOWNLOAD_TYPE_XLSX = 'xlsx';

  const PROCESS_TIMEOUT = 180;
  const EXCEL_SEPARATOR = 'separator';
  const CUSTOM_SEARCH_MAX_LIMIT = 500;
  const DOWNLOAD_DIR = 'public://downloads';

  /**
   * Provide a list with the available download types.
   *
   * @return array
   *   The array keys can be any of the DOWNLOADS_TYPE_* constants. The
   *   value is used as the label.
   */
  public function getAvailableDownloadTypes();

  /**
   * Get the caption used in downloads.
   *
   * @return string
   *   The caption as a string.
   */
  public function getDownloadCaption();

  /**
   * Get the source of the download.
   *
   * @return \Drupal\hpc_downloads\Interfaces\HPCDownloadSourceInterface
   *   The download source.
   */
  public function getDownloadSource();

  /**
   * Get the cache contexts for a download.
   *
   * @return string[]
   *   An array of cache context tokens, used to generate a cache ID.
   */
  public function getCacheContexts();

}
