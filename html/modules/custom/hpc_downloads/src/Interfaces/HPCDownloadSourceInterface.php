<?php

namespace Drupal\hpc_downloads\Interfaces;

/**
 * Interface for all download sources.
 */
interface HPCDownloadSourceInterface {

  /**
   * Get the plugin to use for retrieving the download data.
   *
   * @return \Drupal\hpc_common\Plugin\HPCPluginInterface
   *   The plugin.
   */
  public function getPlugin();

  /**
   * Build the download dialog.
   */
  public function buildDialog();

  /**
   * Get the download type to use.
   */
  public function getType();

  /**
   * Get the options for the download dialog.
   */
  public function getDialogOptions();

  /**
   * Get the download method to be used for a download source.
   */
  public function getDownloadMethod();

  /**
   * Fetch the actual download data.
   */
  public function getData();

  /**
   * Fetch the meta data for a download.
   */
  public function getMetaData();

  /**
   * Get download file name.
   */
  public function getDownloadFileName($type);

}
