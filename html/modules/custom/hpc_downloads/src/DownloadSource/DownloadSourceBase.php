<?php

namespace Drupal\hpc_downloads\DownloadSource;

use Drupal\Core\StringTranslation\StringTranslationTrait;

use Drupal\hpc_downloads\Interfaces\HPCDownloadPluginInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadSourceInterface;

/**
 * Abtract base class for all download sources.
 */
abstract class DownloadSourceBase implements HPCDownloadSourceInterface {

  use StringTranslationTrait;

  /**
   * The plugin for a download source.
   *
   * @var mixed
   */
  protected $plugin;

  /**
   * The plugin for a download source..
   *
   * @var string
   */
  protected $downloadMethod;

  /**
   * Public constructor.
   *
   * @param \Drupal\hpc_downloads\Interfaces\HPCDownloadPluginInterface $plugin
   *   The plugin used for a download source.
   */
  public function __construct(HPCDownloadPluginInterface $plugin) {
    $this->plugin = $plugin;
  }

  /**
   * Build the download dialog.
   */
  abstract public function buildDialog();

  /**
   * Get the download type to use.
   *
   * @see DownnloadController::getPluginFromRequest()
   */
  abstract public function getType();

  /**
   * Get the download dialog options.
   */
  abstract public function getDialogOptions();

  /**
   * Get the download data.
   */
  abstract public function getData();

  /**
   * Get the download meta data.
   */
  abstract public function getMetaData();

  /**
   * Get the download file name.
   */
  abstract public function getDownloadFileName($type);

  /**
   * Get the plugin that is providing the data.
   */
  public function getPlugin() {
    return $this->plugin;
  }

  /**
   * Set the download method to use.
   */
  public function setDownloadMethod($type) {
    switch ($type) {
      case 'xls':
      case 'xlsx':
        $this->downloadMethod = '\Drupal\hpc_downloads\DownloadMethods\Excel';
        return TRUE;

      case 'png':
        $this->downloadMethod = '\Drupal\hpc_downloads\DownloadMethods\PNG';
        return TRUE;

      case 'pdf':
        $this->downloadMethod = '\Drupal\hpc_downloads\DownloadMethods\PDF';
        return TRUE;
    }
    return NULL;
  }

  /**
   * Get the download method to use.
   */
  public function getDownloadMethod() {
    return $this->downloadMethod;
  }

}
