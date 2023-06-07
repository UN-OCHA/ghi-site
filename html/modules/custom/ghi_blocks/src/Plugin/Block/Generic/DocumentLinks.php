<?php

namespace Drupal\ghi_blocks\Plugin\Block\Generic;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerGroup;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;

/**
 * Provides a 'Document link' block.
 *
 * @Block(
 *  id = "generic_document_links",
 *  admin_label = @Translation("Document links"),
 *  category = @Translation("Generic elements"),
 *  default_title = @Translation("Publications"),
 *  config_forms = {
 *    "documents" = {
 *      "title" = @Translation("Documents"),
 *      "callback" = "documentsForm",
 *      "base_form" = TRUE
 *    },
 *    "display" = {
 *      "title" = @Translation("Display"),
 *      "callback" = "displayForm"
 *    }
 *  }
 * )
 */
class DocumentLinks extends GHIBlockBase implements MultiStepFormBlockInterface, OverrideDefaultTitleBlockInterface, ConfigurableTableBlockInterface {

  use ConfigurationContainerTrait;
  use ConfigurationContainerGroup;

  /**
   * {@inheritdoc}
   */
  public function buildContent() {

    // Get the config.
    $conf = $this->getBlockConfig();

    // Get the items.
    $items = $this->getConfiguredItems($conf['documents']['documents']);
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

    $link = NULL;
    if (!empty($conf['display']['publications_url'])) {
      $link = Link::fromTextAndUrl($this->t('View all publications'), Url::fromUri($conf['display']['publications_url']));
      $link->getUrl()->setOptions([
        'attributes' => [
          'class' => ['cd-button', 'external'],
        ],
      ]);
    }

    return $tabs ? array_filter([
      [
        '#theme' => 'tab_container',
        '#tabs' => $tabs,
      ],
      $link ? $link->toRenderable() : NULL,
    ]) : NULL;
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'documents' => [
        'documents' => [],
      ],
      'display' => [
        'publications_url' => NULL,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform($is_new = FALSE) {
    return 'documents';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitleSubform() {
    return 'display';
  }

  /**
   * {@inheritdoc}
   */
  public function documentsForm(array $form, FormStateInterface $form_state) {
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
          'formatted_date' => $this->t('Date'),
          'configured_languages' => $this->t('Languages'),
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
  public function displayForm(array $form, FormStateInterface $form_state) {
    $form['publications_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Publications URL'),
      '#description' => $this->t('Add an optional link to an external source of publications.'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'publications_url'),
      '#element_validate' => [
        [LinkWidget::class, 'validateUriElement'],
      ],
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
