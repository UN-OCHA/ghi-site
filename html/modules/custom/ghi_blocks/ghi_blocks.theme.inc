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
use Drupal\ghi_plans\Entity\Plan;

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
  $timestamp = !empty($document['date']) ? strtotime($document['date']) : NULL;
  $date = $timestamp ? $date_formatter->format($timestamp, 'custom', 'd M Y') : NULL;

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

  // Create download dropdown text. The files might be all of the same type but
  // it's not guaranteed. It depends on how the 'document_link' form element is
  // setup, which handles the link validation. So to be sure, we check again
  // here.
  $shared_filetype = count(array_unique($filetypes)) == 1 ? reset($filetypes) : NULL;
  $variables['filetype'] = $shared_filetype ? strtoupper($shared_filetype) : NULL;
  $variables['button_title'] = t('Download @filetype', ['@filetype' => $shared_filetype ? strtoupper($shared_filetype) : NULL]);
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
  foreach ($variables['contacts'] as $key => $contact) {
    $variables['contacts'][$key] = $contact->toArray();
  }
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
  $labels = [];
  $label_additions = [];

  foreach ($sections as $key => $section) {
    $label = $section->label();
    $label_additions[$key] = NULL;
    if ($section->hasField('field_base_object')) {
      $base_object = $section->get('field_base_object')->entity;
      if ($base_object instanceof Plan) {
        $label = $base_object->getYear();
        $label_additions[$key] = $base_object->getPlanTypeShortLabel(FALSE);
      }
      elseif (!$base_object->type->needsYear && $base_object->hasField('field_year')) {
        $label = $base_object->get('field_year')->value;
      }
      else {
        $label = $section_manager->getShortName($base_object, TRUE, TRUE) ?? $label;
      }
      $labels[$key] = $label;
    }
  }
  foreach ($sections as $key => $section) {
    if (empty($labels[$key])) {
      continue;
    }
    $label = $labels[$key];
    if (count(array_keys($labels, $label)) > 1 && !empty($label_additions[$key])) {
      // We need to add an additional element to distinguish non-unique labels.
      $label .= ' (' . $label_additions[$key] . ')';
    }
    if ($current_section->id() == $section->id()) {
      $option_value = ['#markup' => $label];
      $variables['title'] = $label;
    }
    else {
      $option_value = $section->toLink($label)->toRenderable();
    }
    $options[$key] = $option_value;
  }

  $variables['item_list'] = count($options) > 1 ? [
    '#theme' => 'item_list',
    '#items' => $options,
  ] : NULL;
}

/**
 * Preprocess a year switcher element.
 *
 * @see \Drupal\ghi_blocks\Traits\HomepageBlockTrait::buildHomepageYearSwitcher()
 */
function ghi_blocks_preprocess_year_switcher(&$variables) {
  $variables['title'] = $variables['current_year'];
  $variables['item_list'] = [
    '#theme' => 'item_list',
    '#items' => $variables['years'],
  ];
}

/**
 * Preprocess a plan status element.
 */
function ghi_blocks_preprocess_plan_status(&$variables) {
  $variables['status'] = Html::getClass($variables['status']);
}

/**
 * Preprocess a dropdown element.
 */
function ghi_blocks_preprocess_ghi_dropdown(&$variables) {
  $variables['dropdown_id'] = Html::getUniqueId('ghi-dropdown');
}

/**
 * Preprocess an image element.
 */
function ghi_blocks_preprocess_ghi_image(&$variables) {
  $style = $variables['style'] ?? NULL;
  $responsive_image_style = $variables['responsive_image_style'] ?? NULL;
  $theme = $responsive_image_style ? 'responsive_image' : 'image';

  // For external urls we want to use imagecache_external.
  $uri = \Drupal::service('file_url_generator')->generateString($variables['url']);
  $url = strpos($uri, 'http') === 0 ? Url::fromUri($uri) : Url::fromUserInput($uri);
  if ($url->isExternal()) {
    $theme = $responsive_image_style ? 'imagecache_external_responsive' : ($style ? 'imagecache_external' : 'image');
  }
  $variables['image'] = [
    '#theme' => $theme,
    '#uri' => $variables['url'],
    '#style_name' => $style,
    '#responsive_image_style_id' => $responsive_image_style ? $responsive_image_style->id() : NULL,
    '#attributes' => array_filter([
      'style' => 'width: 100%',
      'alt' => $variables['alt'],
      'title' => $variables['title'],
    ]),
  ];
  $variables['id'] = Html::getUniqueId('ghi-image');
  $variables['caption_id'] = Html::getUniqueId('ghi-image-caption');
}

/**
 * Preprocess a message element.
 */
function ghi_blocks_preprocess_ghi_message(&$variables) {
  $variables['type'] = $variables['type'] ?? 'info';
}
