<?php

namespace Drupal\hpc_downloads\Interfaces;

/**
 * Interface declaration for batched excel downloads.
 */
interface HPCDownloadExcelInterface extends HPCDownloadPluginInterface {

  /**
   * Build the meta data for the download.
   */
  public function buildMetaData();

  /**
   * Build the download data.
   */
  public function buildDownloadData();

  /**
   * Build the download table.
   */
  public function buildDownloadTable();

}
