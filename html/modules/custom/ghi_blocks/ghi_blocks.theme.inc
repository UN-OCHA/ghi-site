<?php

/**
 * @file
 * Preprocess hooks and general purpose theme related functions for GHI Blocks.
 */

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Helpers\GlobalMapHelper;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\DocumentLink;
use Drupal\ghi_sections\Entity\SectionNodeInterface;

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
function ghi_blocks_preprocess_map(&$variables) {
  $outer_classes = [
    'map-wrapper-outer',
    'map-wrapper-' . $variables['chart_id'],
  ];
  $variables['attributes'] = new Attribute(['class' => $outer_classes]);
  $variables['#attached']['drupalSettings']['map_config'] = GlobalMapHelper::getGlobalMapSettings();
  $variables['#attached']['drupalSettings']['mapbox'] = GlobalMapHelper::getMapboxConfig();
}

/**
 * Preprocess a plan attachment map.
 */
function ghi_blocks_preprocess_plan_overview_map(&$variables) {
  ghi_blocks_preprocess_map($variables);
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
  ghi_blocks_preprocess_map($variables);

  if (!empty($variables['attachment_switcher'])) {
    $variables['attributes']['class'][] = Html::getClass('has-attachment-switcher');
  }
  $variables['attachment_select'] = $variables['attachment_switcher'] ?? '';
}

/**
 * Preprocess a plan attachment map.
 */
function ghi_blocks_preprocess_plan_operational_presence_map(&$variables) {
  ghi_blocks_preprocess_map($variables);
  if (!empty($variables['view_switcher'])) {
    $variables['attributes']['class'][] = Html::getClass('has-view-switcher');
  }
  if (!empty($variables['object_switcher'])) {
    $variables['attributes']['class'][] = Html::getClass('has-object-switcher');
  }
  $variables['view_select'] = $variables['view_switcher'] ?? '';
  $variables['object_select'] = $variables['object_switcher'] ?? '';
}

/**
 * Preprocess a plan cluster contacts block.
 */
function ghi_blocks_preprocess_plan_cluster_contacts(&$variables) {
  foreach ($variables['contacts'] as $key => $contact) {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\ContactAttachment $contact */
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
    $filesize = ByteSizeMarkup::create($document['filesize']);
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
  /** @var \Drupal\ghi_sections\Entity\SectionNodeInterface[] $sections */
  $sections = $variables['sections'];
  /** @var \Drupal\ghi_sections\Entity\SectionNodeInterface $current_section */
  $current_section = $variables['current_section'];

  $options = [];

  // We have 3 levels of disambiguation. In order to do that, we first need to
  // collect the normal short labels and the longer labels.
  // @see \Drupal\ghi_sections\Entity\Section::getSectionSwitcherOption().
  $labels = [];
  $labels_long = [];

  // Make sure we really only have section nodes here.
  $sections = array_filter($sections, function ($section) {
    return $section instanceof SectionNodeInterface;
  });

  // Assemble the labels and long labels so that we know in the next step
  // whether we have to disambiguate or not.
  foreach ($sections as $key => $section) {
    $option = $section->getSectionSwitcherOption();
    $labels[$key] = $option['label'];
    $labels_long[$key] = $option['label_long'];
  }

  // Now assemble the actual section switcher options.
  foreach ($sections as $key => $section) {
    if (empty($labels[$key])) {
      continue;
    }
    $option = $section->getSectionSwitcherOption();
    $label = $option['label'];

    // Do the disambiguation if necessary.
    if (count(array_keys($labels, $label)) > 1 && !empty($option['label_long'])) {
      // We need to use the long label to distinguish non-unique labels.
      $label = $option['label_long'];
    }
    if (count(array_keys($labels_long, $label)) > 1 && !empty($option['label_full'])) {
      // We need to use the full label to distinguish non-unique long labels.
      $label = $option['label_full'];
    }

    // Create the actual select option as a list item value.
    if ($current_section->id() == $section->id()) {
      $option_value = [
        '#markup' => $label,
        '#wrapper_attributes' => ['class' => ['active']],
      ];
      $variables['title'] = $section->getSectionSwitcherTitle();
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
 * Preprocess a message element.
 */
function ghi_blocks_preprocess_ghi_message(&$variables) {
  $variables['type'] = $variables['type'] ?? 'info';
}
