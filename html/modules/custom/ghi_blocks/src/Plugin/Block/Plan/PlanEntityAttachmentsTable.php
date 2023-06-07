<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_blocks\Interfaces\AttachmentTableInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\AttachmentTableTrait;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\ghi_plans\ApiObjects\Entities\PlanEntity;
use Drupal\hpc_downloads\Interfaces\HPCDownloadExcelInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPNGInterface;

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
class PlanEntityAttachmentsTable extends GHIBlockBase implements ConfigurableTableBlockInterface, MultiStepFormBlockInterface, OverrideDefaultTitleBlockInterface, AttachmentTableInterface, HPCDownloadExcelInterface, HPCDownloadPNGInterface {

  use ConfigurationContainerTrait;
  use AttachmentTableTrait;

  const TABLE_TYPE_GROUPED = 'grouped';
  const TABLE_TYPE_FLAT = 'flat';

  /**
   * Flag to indicate if this is an export context.
   *
   * @var bool
   */
  private $isExport = FALSE;

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
        '#progress_groups' => TRUE,
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
    $entities = $this->getCurrentEntities();
    return count($entities) > 1;
  }

  /**
   * Get the current entity object.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface|null
   *   The entity object.
   */
  private function getCurrentEntity() {
    $entity_id = $this->getCurrentEntityId();
    if (!$entity_id) {
      return NULL;
    }
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\EntityQuery $query */
    $query = $this->getQueryHandler('entity');
    return $query->getEntity('planEntity', $entity_id) ?? $query->getEntity('governingEntity', $entity_id);
  }

  /**
   * Get the current entity id for this block.
   *
   * @return int
   *   The entity id.
   */
  private function getCurrentEntityId() {
    $entity_ids = [];
    if (!$this->isGroupedTable()) {
      $entity_ids = array_keys($this->getCurrentEntityOptionsFlat());
    }
    else {
      $grouped_entities = $this->getCurrentEntityOptionsGrouped();
      foreach ($grouped_entities as $grouped_entity) {
        $entity_ids = array_merge($entity_ids, array_keys($grouped_entity));
      }
    }
    $conf = $this->getBlockConfig();
    $entity_id = $this->requestStack->getCurrentRequest()->request->get('entity_id') ?? $conf['display']['default_entity'];
    if ($entity_id && in_array($entity_id, $entity_ids)) {
      return $entity_id;
    }
    return reset($entity_ids);
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
    $entity_id_options = array_keys($this->getCurrentEntityOptionsFlat());

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
              '@description' => $entity->description ?? '',
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
        $cell = $item_type->getTableCell();
        $cell['data-progress-group'] = $item_type->getColumnType() == 'percentage' ? 'percentage' : NULL;
        $row[] = $cell;

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

    if (empty($rows)) {
      return NULL;
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
      if (!$entity) {
        return NULL;
      }
      if ($entity->id() == $entity_id) {
        return TRUE;
      }
      if ($entity instanceof PlanEntity) {
        return in_array($entity_id, $entity->getParentIds());
      }
      return NULL;
    });
    return $attachments;
  }

  /**
   * Get the entities from the current set of attachments.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[]
   *   An array of entity objects keyed by the entity id.
   */
  private function getCurrentEntities() {
    $attachments = $this->getSelectedAttachments();
    $entities = [];
    foreach ($attachments as $attachment) {
      $entity = $attachment->getSourceEntity();
      $entities[$entity->id()] = $entity;
    }
    // Sort the entities.
    uasort($entities, function ($_a, $_b) {
      return $_a->sort_key - $_b->sort_key;
    });
    return $entities;
  }

  /**
   * Get the grouped entity options from the current set of attachments.
   *
   * @return array
   *   Grouped options array, key is the entity id, value is the entity name.
   */
  private function getCurrentEntityOptionsGrouped() {
    $entities = $this->getCurrentEntities();
    $entity_options = [];
    foreach ($entities as $entity) {
      if ($entity instanceof PlanEntity) {
        // @codingStandardsIgnoreStart
        // if ($parent_id = $entity->getMainLevelParentId()) {
        //   $parent_entity = PlanEntityHelper::getPlanEntity($parent_id);
        //   $parent_entity_name = $parent_entity->getEntityName();
        //   $group_name = $parent_entity->getGroupName();
        //   $entity_options[$group_name] = $entity_options[$group_name] ?? [];
        //   $entity_options[$group_name][$parent_entity->id()] = $parent_entity_name;
        //   ksort($entity_options[$group_name]);
        // }
        // else {
        //   $group_name = $entity->getGroupName();
        //   $entity_options[$group_name] = $entity_options[$group_name] ?? [];
        //   $entity_options[$group_name][$entity->id()] = $entity->getEntityName();
        //   ksort($entity_options[$group_name]);
        // }
        // @codingStandardsIgnoreEnd
        $group_name = $entity->getGroupName();
        $entity_options[$group_name] = $entity_options[$group_name] ?? [];
        $entity_options[$group_name][$entity->id()] = $entity->getEntityName();
        ksort($entity_options[$group_name]);
      }
      else {
        $entity_name = $entity->getEntityName();
        $entity_options[$entity_name] = $entity_options[$entity_name] ?? [];
        $entity_options[$entity_name][$entity->id()] = $entity->getEntityName();
        ksort($entity_options[$entity_name]);
      }
    }
    return $entity_options;
  }

  /**
   * Get the entity options from the current set of attachments.
   *
   * @return array
   *   Options array, key is the entity id, value is the entity name.
   */
  private function getCurrentEntityOptionsFlat() {
    return array_map(function ($entity) {
      return $entity->getEntityName();
    }, $this->getCurrentEntities());
  }

  /**
   * Get the entity switcher.
   *
   * @return array
   *   A render array for the entity switcher.
   */
  private function getEntitySwitcher() {
    // Get the attachments and configured columns.
    $entity_options = $this->getCurrentEntityOptionsGrouped();
    $current_entity = $this->getCurrentEntity();
    $entity_description = $current_entity?->description ?? NULL;

    $build = [
      '#type' => 'container',
      [
        '#theme' => 'ajax_switcher',
        '#element_key' => 'entity_id',
        '#options' => $entity_options,
        '#default_value' => $current_entity?->id(),
        '#wrapper_id' => Html::getId('block-' . $this->getUuid()),
        '#plugin_id' => $this->getPluginId(),
        '#block_uuid' => $this->getUuid(),
        '#uri' => $this->getCurrentUri(),
      ],
      [
        '#markup' => Markup::create($entity_description),
      ],
    ];
    if ($current_entity instanceof PlanEntity && $plan_entity_parents = $current_entity->getPlanEntityParents()) {
      $contribute_items = array_map(function (PlanEntity $plan_entity) {
        return [
          [
            '#theme' => 'hpc_icon',
            '#icon' => 'check_circle',
            '#tag' => 'span',
          ],
          [
            '#markup' => $plan_entity->getEntityName(),
          ],
        ];
      }, $plan_entity_parents);
      $build[] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['contribution-wrapper'],
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t('Contributes to'),
        ],
        [
          '#theme' => 'item_list',
          '#items' => $contribute_items,
        ],
      ];
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
      'attachments' => [
        'entity_attachments' => [
          'entities' => [
            'entity_ids' => NULL,
          ],
          'attachments' => [
            'entity_type' => NULL,
            'attachment_type' => NULL,
            'attachment_id' => NULL,
          ],
        ],
      ],
      'table' => [
        'columns' => [],
      ],
      'display' => [
        'table_type' => self::TABLE_TYPE_GROUPED,
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
   * {@inheritdoc}
   */
  public function getTitleSubform() {
    return 'display';
  }

  /**
   * Form callback for the attachments form.
   */
  public function attachmentsForm(array $form, FormStateInterface $form_state) {
    $form['entity_attachments'] = [
      '#type' => 'entity_attachment_select',
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'entity_attachments'),
      '#element_context' => $this->getBlockContext(),
      '#attachment_options' => [
        'attachment_prototypes' => TRUE,
        'attachment_types' => ['indicator'],
      ],
      '#attachment_type' => 'indicator',
    ];
    return $form;
  }

  /**
   * Form callback for the table form.
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

    $form['default_entity'] = [
      '#type' => 'select',
      '#title' => $this->t('Default entity'),
      '#description' => $this->t('Please select the entity that will show by default. If multiple entities are available to this widget, then the user can select to see data for the other entities by using a drop-down selector.'),
      '#options' => $this->getCurrentEntityOptionsFlat(),
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
   * {@inheritdoc}
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
        'disaggregation_modal' => TRUE,
        'select_monitoring_period' => TRUE,
      ],
      'spark_line_chart' => [],
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
      return $attachment instanceof DataAttachment && !empty($attachment->getSourceEntity());
    });
    $this->groupAndSortAttachments($attachments);
    return $attachments;
  }

  /**
   * Group and sort attachments.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment[] $attachments
   *   The attachments to group and sort.
   */
  private function groupAndSortAttachments(array &$attachments) {
    // Sort by entity.
    $entities = [];
    foreach ($attachments as $attachment) {
      $source_entity = $attachment->getSourceEntity();
      $entity_id = $source_entity->id();
      $entities[$entity_id] = $entities[$entity_id] ?? [
        'entity' => $source_entity,
        'attachments' => [],
      ];
      $entities[$entity_id]['attachments'][] = $attachment;
    }
    uasort($entities, function ($_a, $_b) {
      return $_a['entity']->sort_key - $_b['entity']->sort_key;
    });
    $attachments = [];
    foreach ($entities as $_entity) {
      uasort($_entity['attachments'], function (DataAttachment $attachment_a, DataAttachment $attachment_b) {
        return strnatcmp($attachment_a->getTitle(), $attachment_b->getTitle());
      });
      $attachments = array_merge($attachments, $_entity['attachments']);
    }
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
