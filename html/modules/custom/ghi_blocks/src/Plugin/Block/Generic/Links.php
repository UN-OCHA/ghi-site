<?php

namespace Drupal\ghi_blocks\Plugin\Block\Generic;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerGroup;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;

/**
 * Provides a 'Links' block.
 *
 * @Block(
 *  id = "links",
 *  admin_label = @Translation("Links"),
 *  category = @Translation("Generic elements"),
 *  title = false,
 *  config_forms = {
 *    "links" = {
 *      "title" = @Translation("Links"),
 *      "callback" = "linksForm",
 *      "base_form" = TRUE
 *    },
 *    "display" = {
 *      "title" = @Translation("Display"),
 *      "callback" = "displayForm"
 *    }
 *  }
 * )
 */
class Links extends GHIBlockBase implements MultiStepFormBlockInterface, OverrideDefaultTitleBlockInterface, ConfigurableTableBlockInterface {

  use ConfigurationContainerTrait;
  use ConfigurationContainerGroup;

  /**
   * {@inheritdoc}
   */
  public function buildContent() {

    // Get the config.
    $conf = $this->getBlockConfig();

    // Get the items.
    $items = $this->getConfiguredItems($conf['links']['links']);
    if (empty($items)) {
      return NULL;
    }

    $rendered = [];

    $context = $this->getBlockContext();
    foreach ($items as $item) {
      /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
      $item_type = $this->getItemTypePluginForColumn($item, $context);
      $rendered[] = $item_type->getRenderArray();
    }
    $build = [
      '#theme' => 'item_list',
      '#items' => array_filter($rendered),
      '#attributes' => [
        'class' => ['links'],
      ],
      // This is important to make the template suggestions logic work in
      // common_design_subtheme.theme.
      '#context' => [
        'plugin_type' => 'links',
        'plugin_id' => $this->getPluginId(),
      ],
      '#gin_lb_theme_suggestions' => FALSE,
    ];
    return $build;
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'links' => [
        'links' => [],
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
    return 'links';
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
  public function linksForm(array $form, FormStateInterface $form_state) {
    $default_value = $this->getDefaultFormValueFromFormState($form_state, 'links');
    $form['links'] = [
      '#type' => 'configuration_container',
      '#title' => $this->t('Configured links'),
      '#title_display' => 'invisible',
      '#item_type_label' => $this->t('Link'),
      '#default_value' => $default_value,
      '#allowed_item_types' => $this->getAllowedItemTypes(),
      '#preview' => [
        'columns' => [
          'image' => $this->t('Image'),
          'label' => $this->t('Title'),
          'url_string' => $this->t('URL'),
          'description' => $this->t('Description'),
        ],
      ],
      '#element_context' => $this->getBlockContext(),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function displayForm(array $form, FormStateInterface $form_state) {
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
      'link' => [],
    ];
    return $item_types;
  }

}
