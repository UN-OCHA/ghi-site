<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_blocks\Interfaces\AttachmentTableInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\AttachmentTableTrait;
use Drupal\ghi_element_sync\IncompleteElementConfigurationException;
use Drupal\ghi_element_sync\SyncableBlockInterface;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\hpc_downloads\Interfaces\HPCDownloadExcelInterface;
use Drupal\node\NodeInterface;

/**
 * Provides a 'PlanEntityAttachmentsTable' block.
 *
 * @Block(
 *  id = "plan_entity_attachments_table",
 *  admin_label = @Translation("Entity Attachments Table"),
 *  category = @Translation("Plan elements"),
 *  data_sources = {
 *    "entities" = "plan_entities_query",
 *    "entity" = "entity_query",
 *    "attachment" = "attachment_query",
 *    "attachment_search" = "attachment_search_query",
 *    "attachment_prototype" = "attachment_prototype_query",
 *  },
 *  default_title = @Translation("Indicator overview"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *    "plan" = @ContextDefinition("entity:base_object", label = @Translation("Plan"), constraints = { "Bundle": "plan" })
 *  },
 *  config_forms = {
 *    "attachments" = {
 *      "title" = @Translation("Attachments"),
 *      "callback" = "attachmentsForm"
 *    },
 *    "table" = {
 *      "title" = @Translation("Table"),
 *      "callback" = "tableForm"
 *    },
 *    "display" = {
 *      "title" = @Translation("Display"),
 *      "callback" = "displayForm",
 *      "base_form" = TRUE
 *    }
 *  }
 * )
 */
class PlanEntityAttachmentsTable extends GHIBlockBase implements ConfigurableTableBlockInterface, MultiStepFormBlockInterface, SyncableBlockInterface, OverrideDefaultTitleBlockInterface, AttachmentTableInterface, HPCDownloadExcelInterface {

  use ConfigurationContainerTrait;
  use AttachmentTableTrait;

  const TABLE_TYPE_GROUPED = 'grouped';
  const TABLE_TYPE_FLAT = 'flat';

  const TABLE_GROUP_SELECTOR_FIRST_LEVEL = 'first_level';
  const TABLE_GROUP_SELECTOR_HIERARCHY = 'hierarchy';

  /**
   * Flag to indicate if this is an export context.
   *
   * @var bool
   */
  private $isExport = FALSE;

  /**
   * {@inheritdoc}
   */
  public static function mapConfig($config, NodeInterface $node, $element_type, $dry_run = FALSE) {
    $base_object = BaseObjectHelper::getBaseObjectFromNode($node);
    $plan = $base_object && $base_object->bundle() == 'plan' ? $base_object : NULL;
    $plan_id = $plan ? $plan->get('field_original_id')->value : NULL;

    if (!property_exists($config, 'entity_select')) {
      if (($config->entity_type ?? NULL) == 'overview' && $base_object = BaseObjectHelper::getBaseObjectFromNode($node)) {
        $entity_id = BaseObjectHelper::getOriginalIdFromEntity($base_object);
        $config->entity_select = (object) [
          'entity_id' => [
            $entity_id => $entity_id,
          ],
        ];
      }
      else {
        throw new IncompleteElementConfigurationException('Incomplete configuration for "plan_attachment_map"');
      }
    }
    $entity_id = $config->entity_select->entity_id;
    $attachment_id = $config->attachment_select->attachment_id ?? NULL;
    $id_format = $config->id_format ?? 'custom_id_prefixed_refcode';

    // Sanity check to prevent importing of misconfigured elements.
    if ($attachment_id && $plan_id) {
      /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentQuery $attachment_query */
      $attachment_query = \Drupal::service('plugin.manager.endpoint_query_manager')->createInstance('attachment_query');
      $attachment = $attachment_query->getAttachment(is_array($attachment_id) ? reset($attachment_id) : $attachment_id);
      if (!$attachment || !$attachment instanceof DataAttachment) {
        $attachment_id = NULL;
      }
      elseif ($attachment && $attachment->getPlanId() != $plan_id) {
        $attachment_id = NULL;
      }
    }
    if (!$attachment_id) {
      throw new IncompleteElementConfigurationException('Incomplete configuration for "plan_attachment_map"');
    }

    $columns = [];
    // Define a transition map.
    $transition_map = [
      'indicator_name' => [
        'target' => 'attachment_label',
        'config' => [
          'id_prefix' => TRUE,
          'id_type' => $id_format,
        ],
      ],
      'unit' => [
        'target' => 'attachment_unit',
      ],
      'data_point' => [
        'target' => 'data_point',
      ],
      'data_point_single' => [
        'target' => 'data_point',
      ],
      'data_point_calculated_progressbar' => [
        'target' => 'data_point',
      ],
      'data_point_calculated_pie_chart' => [
        'target' => 'data_point',
      ],
      'data_point_monitoring_period' => [
        'target' => 'monitoring_period',
      ],
    ];

    foreach ($config->table_columns as $incoming_item) {
      $source_type = !empty($incoming_item->element) ? $incoming_item->element : NULL;
      if (!$source_type || !array_key_exists($source_type, $transition_map)) {
        continue;
      }
      // Apply generic config based on the transition map.
      $transition_definition = $transition_map[$source_type];
      $item = [
        'item_type' => $transition_definition['target'],
        'config' => [
          'label' => property_exists($incoming_item, 'label') ? $incoming_item->label : NULL,
        ],
      ];
      if (array_key_exists('config', $transition_definition)) {
        $item['config'] += $transition_definition['config'];
      }

      // Do special processing for individual item types.
      $value = property_exists($incoming_item, 'value') ? $incoming_item->value : NULL;
      if ($transition_definition['target'] == 'data_point' && $value === NULL) {
        continue;
      }
      switch ($transition_definition['target']) {

        case 'data_point':
          $value->data_points[0] = $value->data_point_1;
          $value->data_points[1] = $value->data_point_2;
          $value->widget = $value->mini_widget;
          unset($value->data_point_1);
          unset($value->data_point_2);
          unset($value->mini_widget);
          $item['config']['data_point'] = (array) $value;
          break;

        default:
          break;
      }
      $columns[] = $item;
    }

    $entity_id = is_array($entity_id) ? array_filter(array_values($entity_id)) : (array) $entity_id;
    $attachment_id = is_array($attachment_id) ? array_filter(array_values($attachment_id)) : (array) $attachment_id;
    return [
      'label' => property_exists($config, 'widget_title') ? $config->widget_title : NULL,
      'label_display' => TRUE,
      'hpc' => [
        'attachments' => [
          'entity_attachments' => [
            'entities' => [
              'entity_ids' => array_combine($entity_id, $entity_id),
            ],
            'attachments' => [
              'entity_type' => $config->attachment_select->entity_type ?? NULL,
              'attachment_type' => $config->attachment_select->attachment_type,
              'attachment_prototype' => $config->attachment_select->attachment_prototype ?? NULL,
              'attachment_id' => array_combine($attachment_id, $attachment_id),
            ],
          ],
        ],
        'table' => [
          'columns' => $columns,
        ],
        'display' => [
          'table_type' => $config->table_type ?? self::TABLE_TYPE_GROUPED,
          'group_selector' => $config->group_selector ?? self::TABLE_GROUP_SELECTOR_FIRST_LEVEL,
          'default_entity' => $config->default_entity ?? reset($entity_id),
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $table_data = $this->buildTableData();
    if (empty($table_data)) {
      return NULL;
    }
    $entity_switcher = NULL;
    $is_grouped = $this->isGroupedTable();
    if ($is_grouped) {
      $entity_switcher = $this->getEntitySwitcher();
    }
    return [
      '#type' => 'container',
      0 => $entity_switcher,
      1 => [
        '#theme' => 'table',
        '#header' => $table_data['header'],
        '#rows' => $table_data['rows'],
        '#sortable' => $is_grouped,
      ],
    ];
  }

  /**
   * Check if the table should be grouped by entity.
   *
   * @return bool
   *   TRUE if the table should grouped, FALSE otherwise.
   */
  private function isGroupedTable() {
    $conf = $this->getBlockConfig();
    if ($conf['display']['table_type'] != self::TABLE_TYPE_GROUPED) {
      return FALSE;
    }
    if ($this->isExport) {
      // Data exports should always be un-grouped.
      return FALSE;
    }
    $entities = $this->getCurrentEntityOptions();
    return count($entities) > 1;
  }

  /**
   * Get the current entity id for this block.
   *
   * @return int
   *   The entity id.
   */
  private function getCurrentEntityId() {
    $entity_options = $this->getCurrentEntityOptions();
    $entity_id = $this->requestStack->getCurrentRequest()->request->get('entity_id');
    if ($entity_id && array_key_exists($entity_id, $entity_options)) {
      return $entity_id;
    }
    return array_key_first($entity_options);
  }

  /**
   * Build the table data for this element.
   *
   * @return array|null
   *   An array with the keys "header" and "rows".
   */
  private function buildTableData() {
    $conf = $this->getBlockConfig();

    // Get the attachments and configured columns.
    $attachments = $this->getSelectedAttachments() ?? [];
    $columns = $this->getConfiguredItems($conf['table']['columns']);
    if (empty($columns) || empty($attachments)) {
      return NULL;
    }

    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment[] $attachments */
    if ($this->isGroupedTable()) {
      $attachments = $this->getAttachmentsForCurrentEntity();
    }

    $context = $this->getBlockContext();
    $current_entity_id = NULL;
    $entity_id_options = array_keys($this->getCurrentEntityOptions());

    $rows = [];
    foreach ($attachments as $attachment) {

      $context['attachment'] = $attachment;

      if (!$this->isGroupedTable() && (empty($current_entity_id) || $current_entity_id != $attachment->source->entity_id) && in_array($attachment->source->entity_id, $entity_id_options)) {
        $entity = $attachment->getSourceEntity();
        $current_entity_id = $attachment->source->entity_id;
        $rows[] = [
          [
            'data' => new FormattableMarkup('@composed_reference: @description', [
              '@composed_reference' => $entity->composed_reference,
              '@description' => $entity->description,
            ]),
            'colspan' => count($columns),
            'class' => 'group-name',
          ],
        ];
      }

      $row = [];
      $skip_row = FALSE;
      foreach ($columns as $column) {

        /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
        $item_type = $this->getItemTypePluginForColumn($column, $context);

        // Then add the value to the row.
        $row[] = [
          'data' => $item_type->getRenderArray(),
          'data-value' => $item_type->getValue(),
          'data-raw-value' => $item_type->getSortableValue(),
          'data-sort-type' => $item_type::SORT_TYPE,
          'data-column-type' => $item_type::ITEM_TYPE,
          'class' => $item_type->getClasses(),
          'export_value' => $item_type->getSortableValue(),
        ];

        // Update the skip row flag. Make it lazy, only check the item type if
        // it still makes a difference.
        $skip_row = $skip_row ? $skip_row : ($skip_row || $item_type->checkFilter() === FALSE);
      }

      // See if filtering needs to be applied.
      if ($skip_row) {
        continue;
      }

      $rows[] = $row;
    }

    return [
      'header' => $this->buildTableHeader($columns),
      'rows' => $rows,
    ];
  }

  /**
   * Get the attachments for the current entity.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface[]
   *   The currently available attachments.
   */
  private function getAttachmentsForCurrentEntity() {
    $attachments = $this->getSelectedAttachments() ?? [];
    $entity_id = $this->getCurrentEntityId();
    $attachments = array_filter($attachments, function (DataAttachment $attachment) use ($entity_id) {
      $entity = $attachment->getSourceEntity();
      return $entity && $entity->id() == $entity_id;
    });
    return $attachments;
  }

  /**
   * Get the entity options from the given set of attachments.
   *
   * @return array
   *   Options array, key is the entity id, value is the entity name.
   */
  private function getCurrentEntityOptions() {
    $attachments = $this->getSelectedAttachments();
    $first_level = ($this->getBlockConfig()['display']['group_selector'] ?? self::TABLE_GROUP_SELECTOR_FIRST_LEVEL) == self::TABLE_GROUP_SELECTOR_FIRST_LEVEL;
    $entity_options = [];
    foreach ($attachments as $attachment) {
      $entity = $attachment->getSourceEntity();
      if ($first_level && !empty($entity->getChildren())) {
        continue;
      }
      $entity_options[$entity->id()] = $entity->getEntityName();
    }
    return $entity_options;
  }

  /**
   * Get the entity switcher.
   *
   * @return array
   *   A render array for the entity switcher.
   */
  private function getEntitySwitcher() {
    // Get the attachments and configured columns.
    $entity_options = $this->getCurrentEntityOptions();
    $attachments = $this->getAttachmentsForCurrentEntity();
    $entity_description = reset($attachments)->getSourceEntity()->description ?? NULL;
    return [
      '#type' => 'container',
      [
        '#theme' => 'ajax_switcher',
        '#element_key' => 'entity_id',
        '#options' => $entity_options,
        '#default_value' => $this->getCurrentEntityId(),
        '#wrapper_id' => Html::getId('block-' . $this->getUuid()),
        '#plugin_id' => $this->getPluginId(),
        '#block_uuid' => $this->getUuid(),
        '#uri' => $this->getCurrentUri(),
      ],
      [
        '#markup' => Markup::create($entity_description),
      ],
    ];
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'attachments' => [
        'entity_attachments' => [
          'entities' => [
            'entity_ids' => NULL,
          ],
          'attachments' => [
            'entity_type' => NULL,
            'attachment_type' => NULL,
            'attachment_id' => [],
          ],
        ],
      ],
      'table' => [
        'columns' => [],
      ],
      'display' => [
        'table_type' => self::TABLE_TYPE_GROUPED,
        'group_selector' => self::TABLE_GROUP_SELECTOR_FIRST_LEVEL,
        'default_entity' => NULL,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform($is_new = FALSE) {
    $conf = $this->getBlockConfig();
    if (empty($conf['attachments']['entity_attachments']['attachments']['attachment_id'])) {
      return 'attachments';
    }
    if (empty($conf['table']['columns'])) {
      return 'table';
    }
    return 'display';
  }

  /**
   * Form callback for the base settings form.
   */
  public function attachmentsForm(array $form, FormStateInterface $form_state) {
    $form['entity_attachments'] = [
      '#type' => 'entity_attachment_select',
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'entity_attachments'),
      '#element_context' => $this->getBlockContext(),
      '#attachment_options' => [
        0 => 'attachment_prototypes',
        'attachment_types' => ['indicator'],
      ],
      '#attachment_type' => 'indicator',
    ];
    return $form;
  }

  /**
   * Form callback for the base settings form.
   */
  public function tableForm(array $form, FormStateInterface $form_state) {
    $default_value = $this->getDefaultFormValueFromFormState($form_state, 'columns');
    if (empty($default_value)) {
      $default_value = [
        [
          'item_type' => 'attachment_label',
          'config' => [
            'label' => (string) $this->t('Indicator'),
          ],
        ],
      ];
    }
    $form['columns'] = [
      '#type' => 'configuration_container',
      '#title' => $this->t('Configured table columns'),
      '#title_display' => 'invisible',
      '#item_type_label' => $this->t('Column'),
      '#default_value' => $default_value,
      '#allowed_item_types' => $this->getAllowedItemTypes(),
      '#preview' => [
        'columns' => [
          'label' => $this->t('Label'),
        ],
      ],
      '#element_context' => $this->getBlockContext(),
      '#row_filter' => TRUE,
    ];
    return $form;
  }

  /**
   * Form callback for the display configuration form.
   */
  public function displayForm(array $form, FormStateInterface $form_state) {
    $attachments = $this->getSelectedAttachments();
    $prototypes = $this->getUniquePrototypes($attachments);
    $table_type_selector = FormElementHelper::getStateSelector($form, ['table_type']);
    $form['table_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Table type'),
      '#options' => [
        self::TABLE_TYPE_GROUPED => $this->t('Grouped by entity'),
        self::TABLE_TYPE_FLAT => $this->t('Flat list'),
      ],
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'table_type') ?? self::TABLE_TYPE_GROUPED,
      '#description' => $this->t('Grouped tables will show the configured attachments grouped by the associated plan entity. Users can switch between the entities using a dropdown selector. Flat tables will display all configured attachments in a single table.'),
    ];
    if (count($prototypes) > 1) {
      $form['table_type']['#default_value'] = self::TABLE_TYPE_GROUPED;
      $form['table_type']['#disabled'] = TRUE;
      $form['table_type']['#description'] .= ' ' . $this->t('<em>Note: This has been disabled because the selected attachments have different attachment prototypes.</em>');
    }
    $form['group_selector'] = [
      '#type' => 'radios',
      '#title' => $this->t('Group selector'),
      '#description' => $this->t('Choose the type of group selector to use.'),
      '#options' => [
        self::TABLE_GROUP_SELECTOR_FIRST_LEVEL => $this->t('Show only first level groups'),
        self::TABLE_GROUP_SELECTOR_HIERARCHY => $this->t('Show full entity hierarchy in selector'),
      ],
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'group_selector') ?? self::TABLE_GROUP_SELECTOR_FIRST_LEVEL,
      '#states' => [
        'visible' => [
          ':input[name="' . $table_type_selector . '"]' => ['value' => self::TABLE_TYPE_GROUPED],
        ],
      ],
    ];

    $form['default_entity'] = [
      '#type' => 'select',
      '#title' => $this->t('Default entity'),
      '#description' => $this->t('Please select the entity that will show by default. If multiple entities are available to this widget, then the user can select to see data for the other entities by using a drop-down selector.'),
      '#options' => $this->getCurrentEntityOptions(),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'default_entity') ?? NULL,
      '#states' => [
        'visible' => [
          ':input[name="' . $table_type_selector . '"]' => ['value' => self::TABLE_TYPE_GROUPED],
        ],
      ],
      '#access' => !empty($attachment_options),
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
    // Based on the current configuration context, we handle the extraction of
    // the attachment prototype differently.
    $is_attachments_form = ($this->formState ? $this->formState->get('current_subform') : NULL) == 'attachments';
    return [
      'page_node' => $this->getPageNode(),
      'plan_object' => $this->getCurrentPlanObject(),
      'base_object' => $this->getCurrentBaseObject(),
      'context_node' => $this->getPageNode(),
      'attachment_prototype' => $this->getAttachmentPrototype(!$is_attachments_form ? $this->getSelectedAttachments() : NULL),
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
      'attachment_label' => [
        'default_label' => $this->t('Indicator'),
      ],
      'attachment_unit' => [],
      'data_point' => [
        'label' => $this->t('Data point'),
        'attachment_prototype' => $this->getAttachmentPrototype($this->getSelectedAttachments()),
      ],
      'monitoring_period' => [],
    ];
    return $item_types;
  }

  /**
   * Get the attachment objects selected for the current block.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment[]
   *   An array of attachment objects.
   */
  private function getSelectedAttachments() {
    $conf = $this->getBlockConfig();
    $attachment_ids = $conf['attachments']['entity_attachments']['attachments']['attachment_id'] ?? [];
    if (empty($attachment_ids)) {
      return NULL;
    }
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentSearchQuery $query */
    $query = $this->getQueryHandler('attachment_search');
    $attachments = $query->getAttachmentsById($attachment_ids);
    // Filter out non-data attachments.
    $attachments = array_filter($attachments, function ($attachment) {
      return $attachment instanceof DataAttachment;
    });
    return $attachments;
  }

  /**
   * {@inheritdoc}
   */
  public function getAttachmentsForEntities(array $entities, $prototype_id = NULL) {
    if (empty($entities)) {
      return NULL;
    }
    $entity_ids = array_map(function ($entity) {
      return $entity->id;
    }, $entities);
    $filter = array_filter([
      'type' => 'indicator',
    ]);

    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentSearchQuery $query */
    $query = $this->getQueryHandler('attachment_search');
    $attachments = $query->getAttachmentsByObject('governingEntity', $entity_ids, $filter) + $query->getAttachmentsByObject('planEntity', $entity_ids, $filter);
    // Filter out non-data attachments.
    $attachments = array_filter($attachments, function ($attachment) {
      return $attachment instanceof DataAttachment;
    });
    return $attachments;
  }

  /**
   * {@inheritdoc}
   */
  public function buildDownloadData() {
    $this->isExport = TRUE;
    return $this->buildTableData();
  }

}
