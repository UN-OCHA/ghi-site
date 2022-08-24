<?php

/**
 * @file
 * Preprocess hooks and general purpose theme related functions for GHI Blocks.
 */

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\DocumentLink;

/**
 * Preprocess the document link box template.
 */
function ghi_blocks_preprocess_document_link_box(&$variables) {

  /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter $date_formatter */
  $date_formatter = \Drupal::service('date.formatter');

  // Get the document data.
  $document = $variables['document']['#document'];

  // Add the truncated title.
  $variables['title'] = Unicode::truncate($document['title'], DocumentLink::TITLE_MAX_LENGTH, TRUE, TRUE);

  // Get the document date.
  $timestamp = strtotime($document['date']);
  $date = $date_formatter->format($timestamp, 'custom', 'd M Y');

  $filetypes = [];

  // Go about the new approach only if "file_details" is not empty.
  if (!empty($document['file_details'])) {
    $variables['download_links'] = [];
    foreach ($document['file_details'] as $language => $value) {
      if (empty($value['target_url']) || !empty($value['disabled'])) {
        continue;
      }
      // Get the file type.
      $filetype = !empty($value['filetype']) ? $value['filetype'] : pathinfo($value['target_url'], PATHINFO_EXTENSION);
      $filetypes[] = $filetype;

      $variables['download_links'][] = ghi_blocks_build_document_download_link($value, $language);
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
  $variables['button_title'] = t('Download @filetype', ['@filetype' => strtoupper($shared_filetype)]);
  if ($shared_filetype && strtolower($shared_filetype) == 'html') {
    $variables['button_title'] = t('Visit external site');
  }

  // Create meta.
  $variables['meta'] = $date ?? NULL;
}

/**
 * Preprocess the document link button template.
 */
function ghi_blocks_preprocess_document_link_button(&$variables) {

  // Get the document data.
  $document = $variables['document'];

  // Go about the new approach only if "file_details" is not empty.
  if (!empty($document['file_details'])) {
    $variables['download_links'] = [];
    foreach ($document['file_details'] as $language => $value) {
      if (empty($value['target_url']) || !empty($value['disabled'])) {
        continue;
      }
      $variables['download_links'][] = ghi_blocks_build_document_download_link($value, $language);
    }
  }
}

/**
 * Preprocess a plan attachment map.
 */
function ghi_blocks_preprocess_plan_overview_map(&$variables) {
  $outer_classes = [
    'map-wrapper-outer',
    'map-wrapper-' . $variables['chart_id'],
  ];
  $variables['outer_classes'] = implode(' ', $outer_classes);
  $variables['map_type'] = 'donut';
}

/**
 * Preprocess a modal for the plan overview map element.
 */
function ghi_blocks_preprocess_plan_overview_map_modal(&$variables) {
  foreach ($variables['items'] as &$item) {
    // Make sure the values are markup objects or strings.
    if (!empty($item['label']) && is_string($item['label'])) {
      $item['label'] = Markup::create($item['label']);
    }
    if (!empty($item['value']) && is_string($item['value'])) {
      $item['value'] = Markup::create($item['value']);
    }
  }
}

/**
 * Preprocess a plan attachment map.
 */
function ghi_blocks_preprocess_plan_attachment_map(&$variables) {
  $outer_classes = [
    'map-wrapper-outer',
    'map-wrapper-' . $variables['chart_id'],
  ];
  if (!empty($variables['attachment_switcher'])) {
    $outer_classes[] = Html::getClass('has-attachment-switcher');
  }
  $variables['outer_classes'] = implode(' ', $outer_classes);
  $variables['attachment_select'] = $variables['attachment_switcher'] ?? '';
}

/**
 * Preprocess a plan attachment map.
 */
function ghi_blocks_preprocess_plan_operational_presence_map(&$variables) {
  $outer_classes = [
    'map-wrapper-outer',
    'map-wrapper-' . $variables['chart_id'],
  ];
  if (!empty($variables['view_switcher'])) {
    $outer_classes[] = Html::getClass('has-view-switcher');
  }
  if (!empty($variables['object_switcher'])) {
    $outer_classes[] = Html::getClass('has-object-switcher');
  }
  $variables['outer_classes'] = implode(' ', $outer_classes);
  $variables['view_select'] = $variables['view_switcher'] ?? '';
  $variables['object_select'] = $variables['object_switcher'] ?? '';
}

/**
 * Preprocess a plan cluster contacts block.
 */
function ghi_blocks_preprocess_plan_cluster_contacts(&$variables) {
  $contacts = $variables['contacts']['#contacts'];
  $variables['contacts'] = [];
  foreach ($contacts as $contact) {
    $variables['contacts'][] = $contact->toArray();
  }
}

/**
 * Preprocess the sidebar slot item.
 */
function ghi_blocks_preprocess_sidebar_slot_item(&$variables) {

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
  $link_title = implode(' | ', array_filter([
    $language ?: 'English',
    strtoupper($filetype),
    $filesize,
  ]));

  // Prepare the download link.
  return Link::fromTextAndUrl($link_title, Url::fromUri($document['target_url'], [
    'attributes' => [
      'download' => basename($document['target_url']),
      'target' => '_blank',
    ],
  ]))->toRenderable();
}

/**
 * Preprocess an ajax switcher element.
 *
 * @see \Drupal\ghi_blocks\Form\AjaxSwitcherForm
 * @see \Drupal\ghi_blocks\Controller\AjaxBlockController
 */
function ghi_blocks_preprocess_ajax_switcher(&$variables) {
  $element_key = $variables['element_key'];
  $options = $variables['options'];
  $plugin_id = $variables['plugin_id'];
  $block_uuid = $variables['block_uuid'];
  $uri = $variables['uri'];
  $query = $variables['query'];
  $default_value = $variables['default_value'];
  $variables['ajax_switcher'] = \Drupal::formBuilder()->getForm('\Drupal\ghi_blocks\Form\AjaxSwitcherForm', $element_key, $plugin_id, $block_uuid, $options, $default_value, $uri, $query);
}

/**
 * Preprocess a section switcher element.
 *
 * @see \Drupal\ghi_blocks\Plugin\Block\Menu\SectionSwitcher
 */
function ghi_blocks_preprocess_section_switcher(&$variables) {
  $sections = $variables['sections'];
  $current_section = $variables['current_section'];
  /** @var \Drupal\ghi_sections\SectionManager $section_manager */
  $section_manager = \Drupal::service('ghi_sections.manager');
  $options = [];

  foreach ($sections as $section) {
    $label = $section->label();
    if ($section->hasField('field_base_object')) {
      $base_object = $section->get('field_base_object')->entity;
      $label = $section_manager->getShortName($base_object, TRUE, TRUE) ?? $label;
    }
    if ($current_section->id() == $section->id()) {
      $option_value = ['#markup' => $label];
    }
    else {
      $option_value = $section->toLink($label)->toRenderable();
    }
    $options[] = $option_value;
  }
  $variables['item_list'] = [
    '#theme' => 'item_list',
    '#items' => $options,
  ];
}
