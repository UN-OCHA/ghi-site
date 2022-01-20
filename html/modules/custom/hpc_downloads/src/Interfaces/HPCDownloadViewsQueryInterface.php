<?php

namespace Drupal\hpc_downloads\Interfaces;

/**
 * Interface declaration for views based downloads.
 */
interface HPCDownloadViewsQueryInterface extends HPCDownloadPluginInterface {

  /**
   * Get the field list for a view.
   */
  public function getFieldList();

  /**
   * Process the download data for a view.
   */
  public function processDownloadData($download_data = NULL);

  /**
   * Build the download data for a view.
   */
  public function buildDownloadData($download_data);

}
