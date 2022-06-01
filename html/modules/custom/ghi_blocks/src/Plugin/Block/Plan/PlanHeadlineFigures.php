<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_element_sync\SyncableBlockInterface;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerGroup;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\node\NodeInterface;

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
 *  }
 * )
 */
class PlanHeadlineFigures extends GHIBlockBase implements ConfigurableTableBlockInterface, SyncableBlockInterface, ContainerFactoryPluginInterface {

  use ConfigurationContainerTrait;
  use ConfigurationContainerGroup;

  const MAX_ITEMS = 6;

  /**
   * {@inheritdoc}
   */
  public static function mapConfig($config, NodeInterface $node, $element_type, $dry_run = FALSE) {
    $items = [];

    // First define a default group. Incoming elements are not grouped, but the
    // target plugin uses grouping.
    $items[] = [
      'item_type' => 'item_group',
      'id' => count($items),
      'config' => [
        'label' => t('Population'),
      ],
      'weight' => 0,
      'pid' => NULL,
    ];
    $items[] = [
      'item_type' => 'item_group',
      'id' => count($items),
      'config' => [
        'label' => t('Financials'),
      ],
      'weight' => 0,
      'pid' => NULL,
    ];
    $items[] = [
      'item_type' => 'item_group',
      'id' => count($items),
      'config' => [
        'label' => t('Presence'),
      ],
      'weight' => 0,
      'pid' => NULL,
    ];

    $group_map = [
      'attachment_data' => 0,
      'funding_data' => 1,
      'entity_counter' => 2,
      'project_counter' => 2,
      'label_value' => 2,
    ];

    // Define a transition map.
    $transition_map = [
      'plan_entities_counter' => [
        'target' => 'entity_counter',
        'config' => ['entity_type' => 'plan'],
      ],
      'governing_entities_counter' => [
        'target' => 'entity_counter',
        'config' => ['entity_type' => 'governing'],
      ],
      'partners_counter' => [
        'target' => 'project_counter',
        'config' => ['data_type' => 'organizations_count'],
      ],
      'projects_counter' => [
        'target' => 'project_counter',
        'config' => ['data_type' => 'projects_count'],
      ],
      'attachment_value' => [
        'target' => 'attachment_data',
      ],
      'original_requirements' => [
        'target' => 'funding_data',
        'config' => ['data_type' => 'original_requirements'],
      ],
      'funding_requirements' => [
        'target' => 'funding_data',
        'config' => ['data_type' => 'current_requirements'],
      ],
      'total_funding' => [
        'target' => 'funding_data',
        'config' => ['data_type' => 'funding_totals'],
      ],
      'outside_funding' => [
        'target' => 'funding_data',
        'config' => ['data_type' => 'outside_funding'],
      ],
      'funding_coverage' => [
        'target' => 'funding_data',
        'config' => ['data_type' => 'funding_coverage'],
      ],
      'funding_gap' => [
        'target' => 'funding_data',
        'config' => ['data_type' => 'funding_gap'],
      ],
      'label_value' => [
        'target' => 'label_value',
      ],
    ];
    foreach ($config->items as $incoming_item) {
      $source_type = !empty($incoming_item->element) ? $incoming_item->element : NULL;
      if (!$source_type || !array_key_exists($source_type, $transition_map)) {
        continue;
      }
      // Apply generic config based on the transition map.
      $transition_definition = $transition_map[$source_type];
      $item = [
        'item_type' => $transition_definition['target'],
        'pid' => $group_map[$transition_definition['target']] ?? 0,
        'id' => count($items),
        'weight' => count($items),
        'config' => [
          'label' => $incoming_item->label,
        ],
      ];
      if (array_key_exists('config', $transition_definition)) {
        $item['config'] += $transition_definition['config'];
      }

      // Do special processing for individual item types.
      $value = $incoming_item->value;
      if (is_object($value) && property_exists($value, 'cluster_restrict') && property_exists($value, 'cluster_tag')) {
        $item['config']['cluster_restrict'] = [
          'type' => $value->cluster_restrict,
          'tag' => $value->cluster_tag,
        ];
      }
      switch ($transition_definition['target']) {
        case 'entity_counter':
          $item['config']['entity_prototype'] = $value;
          break;

        case 'label_value':
          $item['config']['value'] = $value;
          break;

        case 'original_requirements':
        case 'funding_requirements':
          $item['config']['scale'] = property_exists($value, 'formatting') ? $value->formatting : 'auto';
          break;

        case 'project_counter':
          if ($value === NULL && empty($incoming_item->label)) {
            // Skip this item entirely.
            continue(2);
          }
          break;

        case 'attachment_data':
          $item['config']['attachment'] = [
            'entity_type' => $value->attachment_select->entity_type,
            'attachment_type' => $value->attachment_select->attachment_type,
            'attachment_id' => $value->attachment_select->attachment_id,
          ];
          $item['config']['data_point'] = [
            'processing' => $value->data_point->processing,
            'calculation' => $value->data_point->calculation,
            'data_points' => [
              0 => $value->data_point->data_point_1,
              1 => $value->data_point->data_point_2,
            ],
            'formatting' => $value->data_point->formatting,
            'widget' => $value->data_point->mini_widget,
          ];
          break;

        default:
          break;
      }
      $items[] = $item;
    }
    return [
      'label' => '',
      'label_display' => FALSE,
      'hpc' => [
        'items' => $items,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $conf = $this->getBlockConfig();

    $items = $this->getConfiguredItems($conf['items']);
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

      /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
      $group_item = $this->getItemTypePluginForColumn($group, $context);

      foreach ($group['children'] as $item) {

        /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
        $item_type = $this->getItemTypePluginForColumn($item, $context);

        $rendered[] = [
          '#type' => 'item',
          '#title' => $item_type->getLabel(),
          0 => $item_type->getRenderArray(),
        ];
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
            'class' => ['key-figures'],
          ],
          // This is important to make the template suggestions logic work in
          // common_design_subtheme.theme.
          '#context' => [
            'plugin_type' => 'key_figures',
            'plugin_id' => $this->getPluginId(),
          ],
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
      'items' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {

    $form['items'] = [
      '#type' => 'configuration_container',
      '#title' => $this->t('Configured headline figures'),
      '#title_display' => 'invisble',
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
   * Get the allowed item types for this element.
   *
   * @return array
   *   An array with the allowed item types, keyed by the plugin id, with the
   *   value being an optional configuration array for the plugin.
   */
  public function getAllowedItemTypes() {
    $item_types = [
      'item_group' => [],
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
        ],
      ],
      'label_value' => [],
    ];
    return $item_types;
  }

}
