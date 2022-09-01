<?php

namespace Drupal\hpc_downloads\DownloadDialog;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Serialization\Json;
use Drupal\hpc_downloads\Helpers\DownloadHelper;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPluginInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadSourceInterface;

/**
 * Download dialog class.
 */
class DownloadDialogPlugin {

  use StringTranslationTrait;

  /**
   * Build a full dialog link for the given plugin.
   *
   * @param \Drupal\hpc_downloads\Interfaces\HPCDownloadPluginInterface $plugin
   *   The plugin handling the download.
   * @param string $text
   *   An optional text string with the content of the download dialog link.
   *   If no text is given, an icon will be used.
   * @param string $title
   *   An optional title string with the title of the download dialog modal.
   *   If no title is given, a default one will be used.
   */
  public function buildDialogLink(HPCDownloadPluginInterface $plugin, $text = NULL, $title = NULL) {

    $download_source = $plugin->getDownloadSource();
    if (!$download_source) {
      return NULL;
    }

    $classes = [];
    if ($text === NULL) {
      // Use an icon if no text is given.
      $text = DownloadHelper::getDownloadIconMarkup();
      $classes = ['btn', 'btn--download-pane'];
    }

    $classes[] = 'link--download-dialog';

    $link_options = $download_source->getDialogOptions();
    $dialog_title = !empty($title) ? $title : $this->t('Downloads');

    $link_url = Url::fromRoute('hpc_downloads.download_dialog', ['download_source_type' => $download_source->getType()]);
    $link_url->setOptions([
      'attributes' => [
        'class' => array_merge(['use-ajax'], $classes),
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => 400,
          'title' => $dialog_title,
        ]),
        'rel' => 'nofollow',
      ],
      'query' => array_filter($link_options),
    ]);
    $link = Link::fromTextAndUrl($text, $link_url)->toRenderable();

    $link['#attached'] = [
      'library' => [
        'hpc_downloads/hpc_downloads',
        'core/drupal.dialog.ajax',
      ],
    ];

    $build = [
      '#theme' => 'hpc_download_link',
      '#link' => $link,
    ];
    return $build;
  }

  /**
   * Build a download link that is used inside the download dialog.
   *
   * @param \Drupal\hpc_downloads\Interfaces\HPCDownloadSourceInterface $download_source
   *   The download source.
   * @param string $download_type
   *   The download type identifier.
   * @param string $label
   *   A label for the link.
   * @param array $options
   *   Opional arguments passed as query arguments to the download callback.
   *
   * @return array
   *   A fully built link render array.
   */
  public function buildDownloadLink(HPCDownloadSourceInterface $download_source, $download_type, $label, array $options) {
    $build = [
      '#type' => 'link',
      '#title' => $label,
      '#url' => Url::fromRoute('hpc_downloads.initiate', [
        'download_source_type' => $download_source->getType(),
        'download_type' => $download_type,
      ]),
      '#attributes' => [
        'data-block-uuid' => $download_source->getPlugin()->getUuid(),
      ],
      '#cache' => [
        'contexts' => $download_source->getPlugin()->getCacheContexts(),
      ],
      '#options' => [
        'query' => $options,
        'attributes' => [
          'class' => [
            'use-ajax',
            'btn--download',
            'btn--centered',
            'download-' . $download_type,
          ],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => 600,
          ]),
        ],
      ],
    ];
    return $build;
  }

}
