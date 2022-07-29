<?php

namespace Drupal\ghi_blocks\Plugin\Block\Section;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerGroup;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;

/**
 * Provides a 'Section collection' block.
 *
 * @Block(
 *  id = "section_collection",
 *  admin_label = @Translation("Section collection"),
 *  category = @Translation("Sections"),
 *  default_title = @Translation("Featured sections"),
 *  config_forms = {
 *    "sections" = {
 *      "title" = @Translation("Sections"),
 *      "callback" = "sectionsForm",
 *      "base_form" = TRUE
 *    },
 *    "display" = {
 *      "title" = @Translation("Display"),
 *      "callback" = "displayForm"
 *    }
 *  }
 * )
 */
class SectionCollection extends GHIBlockBase implements ConfigurableTableBlockInterface, MultiStepFormBlockInterface, OverrideDefaultTitleBlockInterface {

  use ConfigurationContainerTrait;
  use ConfigurationContainerGroup;

  /**
   * {@inheritdoc}
   */
  public function buildContent() {

    // Get the config.
    $conf = $this->getBlockConfig();

    $items = $this->getConfiguredItems($conf['sections']['items'] ?? []);
    if (empty($items)) {
      return NULL;
    }

    $context = $this->getBlockContext();
    $tree = $this->buildTree($items);
    if (empty($tree)) {
      return NULL;
    }

    $cache_tags = [];
    $tabs = [];
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
        $_build = $item_type->getRenderArray();
        $rendered[] = $_build;
        $cache_tags = Cache::mergeTags($cache_tags, $_build['#cache']['tags'] ?? []);
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
            'class' => ['section-collection'],
          ],
          // This is important to make the template suggestions logic work in
          // common_design_subtheme.theme.
          '#context' => [
            'plugin_type' => 'section_collection',
            'plugin_id' => $this->getPluginId(),
          ],
          '#gin_lb_theme_suggestions' => FALSE,
        ],
      ];
    }

    if (empty($tabs)) {
      return NULL;
    }

    $build = [];
    $build[] = [
      '#theme' => 'tab_container',
      '#tabs' => $tabs,
      '#cache' => [
        'tags' => $cache_tags,
      ],
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
      'sections' => [
        'items' => [],
      ],
      'display' => [
        'comment' => NULL,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform($is_new = FALSE) {
    return 'sections';
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
  public function sectionsForm(array $form, FormStateInterface $form_state) {
    $form['items'] = [
      '#type' => 'configuration_container',
      '#title' => $this->t('Configured section teasers'),
      '#title_display' => 'invisible',
      '#description' => $this->t('You can add multiple grouped section teasers. Each group will show as a separate tab in the frontend. Items not added to any group will not display in the frontend. A single group will not display in the frontend.'),
      '#item_type_label' => $this->t('Section teaser'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'items'),
      '#allowed_item_types' => $this->getAllowedItemTypes(),
      '#preview' => [
        'columns' => [
          'label' => $this->t('Label'),
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
    return $form;
  }

  /**
   * Get the custom context for this block.
   *
   * @return array
   *   An array with context data or query handlers.
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
      'section_teaser' => [],
    ];
    return $item_types;
  }

}
