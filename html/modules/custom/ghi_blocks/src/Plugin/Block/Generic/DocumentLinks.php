<?php

namespace Drupal\ghi_blocks\Plugin\Block\Generic;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Element\DocumentLink;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_element_sync\SyncableBlockInterface;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerGroup;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\node\NodeInterface;

/**
 * Provides an 'External Widget' block.
 *
 * @Block(
 *  id = "generic_document_links",
 *  admin_label = @Translation("Document links"),
 *  category = @Translation("Generic elements"),
 *  title = false
 * )
 */
class DocumentLinks extends GHIBlockBase implements SyncableBlockInterface, ConfigurableTableBlockInterface {

  use ConfigurationContainerTrait;
  use ConfigurationContainerGroup;

  /**
   * {@inheritdoc}
   */
  public static function mapConfig($config, NodeInterface $node, $element_type, $dry_run = FALSE) {
    $documents = [];

    // First define a default group. Incoming elements are not grouped, but the
    // target plugin uses grouping.
    $documents[] = [
      'item_type' => 'item_group',
      'id' => 0,
      'config' => [
        'label' => t('Population'),
      ],
      'weight' => 0,
      'pid' => NULL,
    ];

    foreach ($config->documents as $item) {
      $timestamp = mktime(0, 0, 0, $item->date->month, $item->date->day, $item->date->year);
      $item->date = date('Y-m-d', $timestamp);

      if (!property_exists($item, 'file_details')) {
        $item->file_details = [
          array_key_first(DocumentLink::LANGUAGES) => [
            'target_url' => $item->target_url,
            'disabled' => FALSE,
            'filesize' => $item->filesize,
            'mimetype' => $item->mimetype,
            'filetype' => $item->filetype,
          ],
        ];
        unset($item->language);
        unset($item->target_url);
        unset($item->filesize);
        unset($item->mimetype);
        unset($item->filetype);
      }
      else {
        $file_details = [];
        foreach ($item->file_details as $details) {
          $details = (array) $details;
          $language = $details['language'];
          unset($details['language']);
          $file_details[$language] = $details;
        }
        $item->file_details = $file_details;
      }
      $documents[] = [
        'item_type' => 'document_link',
        'pid' => 0,
        'id' => count($documents),
        'weight' => count($documents),
        'config' => [
          'label' => $item->title,
          'value' => (array) $item,
        ],
      ];
    }

    return [
      'label' => '',
      'label_display' => TRUE,
      'hpc' => [
        'documents' => $documents,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {

    // Get the config.
    $conf = $this->getBlockConfig();

    // Get the items.
    $items = $this->getConfiguredItems($conf['documents']);
    if (empty($items)) {
      return NULL;
    }

    // Build the tree.
    $tree = $this->buildTree($items);
    if (empty($tree)) {
      return NULL;
    }

    $tabs = [];
    $context = $this->getBlockContext();
    foreach ($tree as $group) {
      $rendered = [];
      if (empty($group['children'])) {
        continue;
      }

      /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
      $group_item = $this->getItemTypePluginForColumn($group, $context);

      foreach ($group['children'] as $item) {
        /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
        $item_type = $this->getItemTypePluginForColumn($item, $context);
        $rendered[] = $item_type->getRenderArray();
      }
      if (empty($rendered)) {
        continue;
      }
      $tabs[] = [
        'title' => [
          '#markup' => $group_item->getLabel(),
        ],
        'items' => [
          '#theme' => 'item_list',
          '#items' => $rendered,
          '#attributes' => [
            'class' => ['document-links'],
          ],
          // This is important to make the template suggestions logic work in
          // common_design_subtheme.theme.
          '#context' => [
            'plugin_type' => 'document_links',
            'plugin_id' => $this->getPluginId(),
          ],
          '#gin_lb_theme_suggestions' => FALSE,
        ],
      ];
    }

    return $tabs ? [
      '#theme' => 'tab_container',
      '#tabs' => $tabs,
    ] : NULL;
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'documents' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    $default_value = $this->getDefaultFormValueFromFormState($form_state, 'documents');
    $form['documents'] = [
      '#type' => 'configuration_container',
      '#title' => $this->t('Configured document links'),
      '#title_display' => 'invisible',
      '#item_type_label' => $this->t('Document link'),
      '#default_value' => $default_value,
      '#allowed_item_types' => $this->getAllowedItemTypes(),
      '#preview' => [
        'columns' => [
          'label' => $this->t('Title'),
          'url_string' => $this->t('Url'),
        ],
      ],
      '#element_context' => $this->getBlockContext(),
      '#groups' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getBlockContext() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedItemTypes() {
    $item_types = [
      'item_group' => [],
      'document_link' => [],
    ];
    return $item_types;
  }

}
