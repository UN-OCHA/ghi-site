<?php

namespace Drupal\hpc_downloads\DownloadSource;

use Drupal\hpc_downloads\Interfaces\HPCDownloadPluginInterface;

/**
 * Defines a download source for a node.
 */
class NodeSource extends DownloadSourceBase {

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return 'node';
  }

  /**
   * {@inheritdoc}
   */
  public function getDialogOptions() {
    return array_filter([
      'uri' => $this->plugin->getCurrentUri(),
      'node' => $this->plugin->getPluginId(),
      'query' => \Drupal::request()->query->all(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildDialog() {
    $dialog_service = \Drupal::service('hpc_downloads.download_dialog_plugin');
    $options = $this->getDialogOptions();

    $links = [];
    // Nodes support only download as PDF.
    $available_download_types = [
      HPCDownloadPluginInterface::DOWNLOAD_TYPE_PDF => $this->t('Download PDF'),
    ];
    foreach ($available_download_types as $download_type => $label) {
      $links[] = $dialog_service->buildDownloadLink($this, $download_type, $label, $options);
    }

    $build = [
      '#theme' => 'hpc_download_dialog',
      '#content' => [
        '#type' => 'container',
        '#children' => $links,
      ],
      '#attached' => ['libraries' => ['hpc_downloads/hpc_downloads']],
      '#cache' => [
        'contexts' => $this->plugin->getCacheContexts(),
      ],
    ];
    return !empty($links) ? $build : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getData() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetaData() {
    return [];
  }

  /**
   * Get the download file name.
   */
  public function getDownloadFileName($type) {
    $label = $this->plugin->label();
    $title = $label . '_as_on_' . date('Y-m-d');
    // Replace any / any html tags that would interfere with the filepath in the
    // filename.
    $title = strip_tags(str_replace(['/', ':', '%', ' '], '_', $title));
    // Remove anything which isn't a word, whitespace, number or any of the
    // following caracters -_~,;[]().
    $title = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $title);
    return $title;
  }

}
