<?php

/**
 * @file
 * Preprocess hooks and general purpose theme related functions for GHI Blocks.
 */

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\ghi_blocks\Plugin\Block\Generic\DocumentLinks;

/**
 * Preprocess the document link box template.
 */
function ghi_blocks_preprocess_document_link_box(&$variables) {

  /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter $date_formatter */
  $date_formatter = \Drupal::service('date.formatter');

  // Get the document data.
  $document = $variables['document']['#document'];

  // Add the truncated title.
  $variables['title'] = Unicode::truncate($document['title'], DocumentLinks::TITLE_MAX_LENGTH, TRUE, TRUE);

  // Get the thumbnail if any.
  $variables['thumbnail'] = NULL;
  if (!empty($document['thumbnail']) && $thumbnail_file = File::load(reset($document['thumbnail']))) {
    /** @var \Drupal\file\Entity\File $thumbnail_file */
    $variables['thumbnail'] = [
      '#theme' => 'image_style',
      '#style_name' => 'document_link_thumbnail',
      '#uri' => $thumbnail_file->getFileUri(),
    ];
  }

  // Get the document date.
  $timestamp = strtotime($document['date']);
  $date = $date_formatter->format($timestamp, 'custom', 'd M Y');

  $filetypes = [];

  // Go about the new approach only if "file_details" is not empty.
  if (!empty($document['file_details'])) {
    $variables['download_links'] = [];
    foreach ($document['file_details'] as $value) {
      if (empty($value['target_url'])) {
        continue;
      }
      // Get the file type.
      $filetype = !empty($value['filetype']) ? $value['filetype'] : pathinfo($value['target_url'], PATHINFO_EXTENSION);
      $filetypes[] = $filetype;

      $variables['download_links'][] = ghi_blocks_build_document_download_link($value, $value['language']);
    }
  }
  // Else stick with the previous way of getting data.
  else {
    // Get the file type.
    $filetype = !empty($document['filetype']) ? $document['filetype'] : pathinfo($document['target_url'], PATHINFO_EXTENSION);
    $filetypes[] = $filetype;

    $variables['download_links'][] = ghi_blocks_build_document_download_link($document);
  }

  // Create download dropdown text. All files should be of the same type as
  // that is imposed by the validation logic in DocumentLink.php, but just to
  // be sure, we check again here.
  $shared_filetype = count(array_unique($filetypes)) == 1 ? reset($filetypes) : NULL;
  $variables['filetype'] = $shared_filetype ? strtoupper($shared_filetype) : NULL;
  $variables['button_title'] = t('Download @filetype', ['@filetype' => $shared_filetype]);
  if ($shared_filetype && strtolower($shared_filetype) == 'html') {
    $variables['button_title'] = t('Visit external site');
  }

  // Create meta.
  $variables['meta'] = $date ?? NULL;
}

/**
 * Build a download link render array for documents.
 *
 * @param array $document
 *   The document array, containing target url and additional meta data.
 * @param string $language
 *   The language label to use for the link.
 *
 * @return mixed[]
 *   A render array.
 */
function ghi_blocks_build_document_download_link(array $document, $language = NULL) {
  $filetype = !empty($document['filetype']) ? $document['filetype'] : pathinfo($document['target_url'], PATHINFO_EXTENSION);

  // Get the filesize.
  $filesize = NULL;
  if (!empty($document['filesize'])) {
    $filesize = format_size($document['filesize']);
  }

  // Link title.
  $link_title = implode(' | ', [
    $language ?: 'English',
    strtoupper($filetype),
    $filesize,
  ]);

  // Prepare the download link.
  return Link::fromTextAndUrl($link_title, Url::fromUri($document['target_url'], [
    'attributes' => [
      'download' => basename($document['target_url']),
      'target' => '_blank',
    ],
  ]))->toRenderable();
}
