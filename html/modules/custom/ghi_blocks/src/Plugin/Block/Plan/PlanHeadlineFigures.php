<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\LineBreak;
use Drupal\ghi_blocks\Traits\BlockCommentTrait;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerGroup;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;

/**
 * Provides a 'PlanHeadlineFigures' block.
 *
 * @Block(
 *  id = "plan_headline_figures",
 *  admin_label = @Translation("Headline Figures"),
 *  category = @Translation("Plan elements"),
 *  title = FALSE,
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *    "plan" = @ContextDefinition("entity:base_object", label = @Translation("Plan"), constraints = { "Bundle": "plan" })
 *  },
 *  config_forms = {
 *    "key_figures" = {
 *      "title" = @Translation("Key figures"),
 *      "callback" = "keyFiguresForm",
 *      "base_form" = TRUE
 *    },
 *    "display" = {
 *      "title" = @Translation("Display"),
 *      "callback" = "displayForm"
 *    }
 *  }
 * )
 */
class PlanHeadlineFigures extends GHIBlockBase implements MultiStepFormBlockInterface, ConfigurableTableBlockInterface, ContainerFactoryPluginInterface {

  use ConfigurationContainerTrait;
  use ConfigurationContainerGroup;
  use BlockCommentTrait;

  const MAX_ITEMS = 20;

  /**
   * {@inheritdoc}
   */
  public function label() {
    // We just want to hide the label always.
    $this->configuration['label_display'] = FALSE;
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $conf = $this->getBlockConfig();

    $items = $this->getConfiguredItems($conf['key_figures']['items'] ?? []);
    if (empty($items)) {
      return;
    }

    $context = $this->getBlockContext();
    $tree = $this->buildTree($items);
    if (empty($tree)) {
      return NULL;
    }

    $tabs = [];
    foreach ($tree as $group) {
      $rendered = [];
      if (empty($group['children'])) {
        continue;
      }

      /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemGroupInterface $group_item */
      $group_item = $this->getItemTypePluginForColumn($group, $context);

      foreach ($group['children'] as $key => $item) {

        /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
        $item_type = $this->getItemTypePluginForColumn($item, $context);

        if ($key == 0 && $item_type instanceof LineBreak) {
          continue;
        }

        $rendered[] = [
          '#type' => 'item',
          '#title' => $item_type->getLabel(),
          0 => $item_type->getRenderArray(),
          '#wrapper_attributes' => ['class' => $item_type->getClasses()],
        ];
      }
      if (empty($rendered)) {
        continue;
      }
      $tab = [
        'title' => [
          '#markup' => $group_item->getLabel(),
        ],
        'items' => [
          '#theme' => 'item_list',
          '#items' => $rendered,
          '#attributes' => [
            'class' => ['key-figures'],
          ],
          '#gin_lb_theme_suggestions' => FALSE,
          // This is important to make the template suggestions logic work in
          // common_design_subtheme.theme.
          '#context' => [
            'plugin_type' => 'key_figures',
            'plugin_id' => $this->getPluginId(),
          ],
        ],
      ];
      if ($group_item->hasLink()) {
        $link = $group_item->getLink();
        $link->getUrl()->setOptions([
          'attributes' => [
            'class' => ['cd-button', 'external'],
          ],
        ]);
        $tab['link'] = $link->toRenderable();
      }
      $tabs[] = $tab;
    }

    if (empty($tabs)) {
      return;
    }

    $build = [];
    $build[] = [
      '#theme' => 'tab_container',
      '#tabs' => $tabs,
    ];
    $comment = $this->buildBlockCommentRenderArray($conf['display']['comment'] ?? NULL);
    if ($comment) {
      $build['comment'] = $comment;
    }
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
      'key_figures' => [
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
    return 'key_figures';
  }

  /**
   * Show the key figures form.
   */
  public function keyFiguresForm(array $form, FormStateInterface $form_state) {
    $form['items'] = [
      '#type' => 'configuration_container',
      '#title' => $this->t('Configured headline figures'),
      '#title_display' => 'invisible',
      '#item_type_label' => $this->t('Headline figure'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'items'),
      '#allowed_item_types' => $this->getAllowedItemTypes(),
      '#preview' => [
        'columns' => [
          'label' => $this->t('Label'),
          'render_array' => $this->t('Value'),
        ],
      ],
      '#element_context' => $this->getBlockContext(),
      '#max_items' => self::MAX_ITEMS,
      '#groups' => TRUE,
    ];
    return $form;
  }

  /**
   * Show the display form.
   */
  public function displayForm(array $form, FormStateInterface $form_state) {
    $form['comment'] = $this->buildBlockCommentFormElement($this->getDefaultFormValueFromFormState($form_state, 'comment'));
    return $form;
  }

  /**
   * Get the custom context for this block.
   *
   * @return array
   *   An array with context data or query handlers.
   */
  public function getBlockContext() {
    return [
      'page_node' => $this->getPageNode(),
      'plan_object' => $this->getCurrentPlanObject(),
      'base_object' => $this->getCurrentBaseObject(),
      'context_node' => $this->getPageNode(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedItemTypes() {
    $item_types = [
      'item_group' => [
        'link' => TRUE,
      ],
      'line_break' => [],
      'funding_data' => [
        'item_types' => [
          'funding_totals',
          'outside_funding',
          'funding_coverage',
          'funding_gap',
          'original_requirements',
          'current_requirements',
        ],
      ],
      'entity_counter' => [],
      'project_counter' => [
        'access' => [
          'plan_costing' => [0, 1, 3],
        ],
      ],
      'attachment_data' => [
        'label' => $this->t('Caseload/indicator value'),
        'access' => [
          'node_type' => ['plan', 'governing_entity'],
        ],
        'data_point' => [
          'widget' => FALSE,
          'select_monitoring_period' => TRUE,
        ],
      ],
      'label_value' => [
        'footnote' => TRUE,
        'custom_logo' => TRUE,
      ],
    ];
    return $item_types;
  }

}
