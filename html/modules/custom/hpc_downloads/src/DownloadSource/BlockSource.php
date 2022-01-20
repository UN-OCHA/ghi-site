<?php

namespace Drupal\hpc_downloads\DownloadSource;

use Drupal\hpc_downloads\DownloadMethods\Excel;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPDFInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPluginInterface;

/**
 * Defines a download source for a block plugin.
 */
class BlockSource extends DownloadSourceBase {

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return 'block';
  }

  /**
   * {@inheritdoc}
   */
  public function getDialogOptions() {
    return array_filter([
      'uri' => $this->plugin->getCurrentUri(),
      'plugin_id' => $this->plugin->getPluginId(),
      'block_uuid' => $this->plugin->getUuid(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildDialog() {
    $dialog_service = \Drupal::service('hpc_downloads.download_dialog_plugin');
    $options = $this->getDialogOptions();

    $links = [];
    $available_download_types = $this->plugin->getAvailableDownloadTypes();
    if (!empty($available_download_types)) {
      foreach ($available_download_types as $download_type => $label) {
        $links[] = $dialog_service->buildDownloadLink($this, $download_type, $label, $options);
      }
    }

    $build = [
      '#theme' => 'hpc_download_dialog',
      '#content' => [
        '#type' => 'container',
        '#children' => $links,
      ],
      '#attached' => ['libraries' => ['hpc_downloads']],
    ];
    return !empty($links) ? $build : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getData() {
    return $this->plugin->buildDownloadData();
  }

  /**
   * {@inheritdoc}
   */
  public function getMetaData() {
    $meta_data = $this->plugin->buildMetaData();
    return Excel::renderTableArray($meta_data);
  }

  /**
   * Get the download file name.
   */
  public function getDownloadFileName($type) {
    $label = $this->plugin->label();
    $title = $this->plugin instanceof HPCDownloadPDFInterface && $type == HPCDownloadPluginInterface::DOWNLOAD_TYPE_PDF ?
      $this->plugin->getDownloadPdfCaption() :
      $label . '_' . $this->plugin->getDownloadCaption();
    $title .= '_' . $this->plugin->getUuid() . '_as_on_' . date('Y-m-d');
    // Replace any / any html tags that would interfere with the filepath in the
    // filename.
    $title = strip_tags(str_replace(['/', ':', '%', ' '], '_', $title));
    // Remove anything which isn't a word, whitespace, number or any of the
    // following caracters -_~,;[]().
    $title = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $title);
    return 'FTS_' . $title;
  }

}
