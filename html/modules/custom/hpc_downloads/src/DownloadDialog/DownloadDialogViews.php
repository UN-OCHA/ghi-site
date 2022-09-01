<?php

namespace Drupal\hpc_downloads\DownloadDialog;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\views\ViewExecutable;
use Drupal\hpc_downloads\DownloadSource\ViewsSource;
use Drupal\hpc_downloads\Helpers\DownloadHelper;

/**
 * Provides a download dialog for views based pages.
 */
class DownloadDialogViews implements TrustedCallbackInterface {

  /**
   * Build a full dialog link for the given plugin.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The plugin handling the download.
   * @param string $text
   *   An optional text string with the content of the download dialog link.
   *   If no text is given, an icon will be used.
   */
  public function buildDialogLink(ViewExecutable $view, $text = NULL) {

    $download_source = new ViewsSource($view);
    if (!$download_source) {
      return NULL;
    }

    $class = get_class($this);
    $build = [
      '#theme' => 'hpc_download_link',
      '#download_source' => $download_source,
      '#text' => $text,
      '#pre_render' => [
        [$class, 'preRender'],
      ],
      '#cache' => [
        'keys' => [
          'download_dialog_link',
          $view->id(),
          $view->current_display,
          $download_source->getType(),
        ],
        'contexts' => ['url'],
      ],
    ];
    return $build;
  }

  /**
   * Pre render callback.
   */
  public static function preRender(array $element) {
    $download_source = $element['#download_source'];
    $text = $element['#text'];

    $classes = NULL;
    if ($text === NULL) {
      // Use an icon if no text is given.
      $text = DownloadHelper::getDownloadIconMarkup();
      $classes = ['btn', 'btn--download-pane'];
    }

    $link_options = $download_source->getDialogOptions();

    $link_url = Url::fromRoute('hpc_downloads.download_dialog', ['download_source_type' => $download_source->getType()]);
    $link_url->setOptions([
      'attributes' => [
        'class' => array_merge(['use-ajax'], (is_array($classes) ? $classes : [])),
        'data-dialog-type' => 'modal',
        // This whole thing gets called from fts_views.module file, function
        // fts_views_preprocess_views_view__plan_code_list_for_iati and hence
        // we don't have access to $this.
        'data-dialog-options' => Json::encode([
          'width' => 400,
          'title' => t('Downloads'),
        ]),
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

    $element['#link'] = $link;
    return $element;
  }

  /**
   * Build a download link that is used inside the download dialog.
   *
   * @param string $download_source
   *   The download source identifier.
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
  public function buildDownloadLink($download_source, $download_type, $label, array $options) {
    $build = [
      '#type' => 'link',
      '#title' => $label,
      '#url' => Url::fromRoute('hpc_downloads.initiate', [
        'download_source_type' => $download_source->getType(),
        'download_type' => $download_type,
      ]),
      '#options' => [
        'query' => $options,
        'attributes' => [
          'class' => [
            'use-ajax',
            'btn',
            'btn--fullwidth',
            'download-' . $download_type,
          ],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => 400,
          ]),
        ],
      ],
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRender'];
  }

}
