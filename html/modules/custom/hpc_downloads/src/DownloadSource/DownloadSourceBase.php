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
   * @var \Drupal\hpc_common\Plugin\HPCPluginInterface
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
   * {@inheritdoc}
   */
  abstract public function buildDialog();

  /**
   * {@inheritdoc}
   *
   * @see DownnloadController::getPluginFromRequest()
   */
  abstract public function getType();

  /**
   * {@inheritdoc}
   */
  abstract public function getDialogOptions();

  /**
   * {@inheritdoc}
   */
  abstract public function getData();

  /**
   * {@inheritdoc}
   */
  abstract public function getMetaData();

  /**
   * {@inheritdoc}
   */
  abstract public function getDownloadFileName($type);

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function getDownloadMethod() {
    return $this->downloadMethod;
  }

}
