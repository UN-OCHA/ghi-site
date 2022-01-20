<?php

namespace Drupal\hpc_downloads\Interfaces;

/**
 * Interface declaration for batched excel downloads.
 */
interface HPCBatchedDownloadExcelInterface {

  /**
   * Initialize the batch.
   */
  public function initBatch(array $record);

  /**
   * Get the batch operations.
   */
  public function getOperations(array $options, array $download_record);

  /**
   * Initialize the plugin before each batch processing.
   */
  public function initPlugin(array $options);

  /**
   * Retrieve the maximum amount of pages.
   */
  public function getMaxPages();

  /**
   * Fetch data for the given page in the result set.
   */
  public function fetchData($page, $limit = NULL);

  /**
   * Retrieve the data for a download.
   *
   * @return array
   *   Array that could be used in theme_table().
   */
  public function getData();

}
