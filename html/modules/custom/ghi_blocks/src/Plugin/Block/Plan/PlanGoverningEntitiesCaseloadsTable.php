<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_blocks\Helpers\AttachmentMatcher;
use Drupal\ghi_blocks\Interfaces\AttachmentTableInterface;
use Drupal\ghi_blocks\Interfaces\ConfigValidationInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\AttachmentTableTrait;
use Drupal\ghi_blocks\Traits\ConfigValidationTrait;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\hpc_downloads\Interfaces\HPCDownloadExcelInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPNGInterface;

/**
 * Provides a 'PlanGoverningEntitiesCaseloadsTable' block.
 *
 * @Block(
 *  id = "plan_governing_entities_caseloads_table",
 *  admin_label = @Translation("Governing Entities Caseloads Table"),
 *  category = @Translation("Plan elements"),
 *  data_sources = {
 *    "entities" = "plan_entities_query",
 *    "attachment_search" = "attachment_search_query",
 *    "attachment_prototype" = "plan_attachment_prototype_query",
 *  },
 *  default_title = @Translation("Cluster caseloads"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *    "plan" = @ContextDefinition("entity:base_object", label = @Translation("Plan"), constraints = { "Bundle": "plan" })
 *  },
 *  config_forms = {
 *    "base" = {
 *      "title" = @Translation("Base settings"),
 *      "callback" = "baseForm",
 *      "base_form" = TRUE
 *    },
 *    "table" = {
 *      "title" = @Translation("Table columns"),
 *      "callback" = "tableForm"
 *    }
 *  }
 * )
 */
class PlanGoverningEntitiesCaseloadsTable extends GHIBlockBase implements ConfigurableTableBlockInterface, MultiStepFormBlockInterface, OverrideDefaultTitleBlockInterface, AttachmentTableInterface, ConfigValidationInterface, HPCDownloadExcelInterface, HPCDownloadPNGInterface {

  use ConfigurationContainerTrait;
  use AttachmentTableTrait;
  use ConfigValidationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $table_data = $this->buildTableData();
    if (empty($table_data)) {
      return NULL;
    }
    return [
      '#theme' => 'table',
      '#header' => $table_data['header'],
      '#rows' => $table_data['rows'],
      '#progress_groups' => TRUE,
    ];
  }

  /**
   * Build the table data for this element.
   *
   * @return array|null
   *   An array with the keys "header" and "rows".
   */
  private function buildTableData() {
    $conf = $this->getBlockConfig();

    // Get the entities and configured columns.
    $entities = $this->getEntityObjects();
    $columns = $this->getConfiguredItems($conf['table']['columns']);

    if (empty($columns) || empty($entities)) {
      return;
    }

    $attachments = $this->getAttachmentsForEntities($entities) ?? [];
    if (empty($attachments) && !$conf['base']['include_non_caseloads']) {
      // No attachments found and the table is not configured to show entities
      // without attachments.
      return;
    }

    $context = $this->getBlockContext();

    $header = [];
    foreach ($columns as $column) {
      /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
      $item_type = $this->getItemTypePluginForColumn($column, $context);
      $header[] = [
        'data' => $item_type->getLabel(),
        'data-sort-type' => $item_type::SORT_TYPE,
        'data-sort-order' => count($header) == 0 ? 'ASC' : '',
        'data-column-type' => $item_type->getColumnType(),
      ];
    }

    // Get the prototype.
    $prototype = $this->getAttachmentPrototype();
    if (!$prototype) {
      return NULL;
    }

    // Filter for the configured attachment prototype id.
    $attachments = $this->filterAttachmentsByPrototype($attachments, $prototype->id);

    // Group by entity.
    $grouped_attachments = $this->groupAttachmentsByEntityId($attachments);

    // Clean the list.
    $grouped_attachments = $this->filterEmptyDescriptionInGroupedAttachments($grouped_attachments);

    // Load the node objects.
    $objects = $this->loadBaseObjectsForEntities($entities);
    if (empty($objects)) {
      return NULL;
    }

    // Sort the entities by name.
    usort($entities, function ($a, $b) {
      return strnatcasecmp($a->getEntityName(), $b->getEntityName());
    });

    $rows = [];
    foreach ($entities as $entity) {
      $base_object = $objects[$entity->id] ?? NULL;
      if (!$base_object) {
        continue;
      }

      $subpage_node = $this->subpageManager->loadSubpageForBaseObject($base_object);

      // Clusters are displayed if they are either published, or the element is
      // configured to also display unpublished clusters.
      if ($subpage_node && !$subpage_node->access('view') && empty($conf['base']['include_unpublished_clusters'])) {
        continue;
      }

      // Usually, clusters without caseloads will not be shown. But the element
      // can be configured to allow to display those clusters anyway.
      if (!array_key_exists($entity->id, $grouped_attachments) && empty($conf['base']['include_non_caseloads'])) {
        continue;
      }

      // Get the attachment.
      $attachments = $grouped_attachments[$entity->id] ?? [];

      // Add the entity and the node object to the context array.
      $context['base_object'] = $base_object;
      $context['context_node'] = $subpage_node;
      $context['entity'] = $entity;
      $context['attachment'] = NULL;

      // Add another row as a group header for clusters with multiple plan
      // caseloads.
      if (count($attachments) > 1) {
        $rows[] = $this->buildTableRow($columns, $context, ['entity_name']);
      }

      if (count($attachments)) {
        // Add one row per caseload.
        foreach ($attachments as $attachment) {
          $context['attachment'] = $attachment;
          $rows[] = $this->buildTableRow($columns, $context, [], count($attachments) > 1);
        }
      }
      elseif (!empty($conf['base']['include_non_caseloads'])) {
        // Or add an empty row if that's allowed.
        $rows[] = $this->buildTableRow($columns, $context);
      }

    }
    $rows = array_filter($rows);

    if (empty($rows)) {
      return;
    }

    return [
      'header' => $header,
      'rows' => $rows,
    ];
  }

  /**
   * Build a table row.
   *
   * @param array $columns
   *   An array of the configured columns.
   * @param array $context
   *   An array of context objects.
   * @param array $limit_item_types
   *   An optional array of machine names for the allowed items. If not empty,
   *   this will skip all item types that are not explicitely listed.
   * @param bool $grouped_attachments
   *   Whether attachments are grouped under the entity they belong to.
   *
   * @return array
   *   The row array.
   */
  private function buildTableRow(array $columns, array $context, array $limit_item_types = [], $grouped_attachments = FALSE) {
    $attachment = $context['attachment'];
    $row = [];
    foreach ($columns as $key => $column) {

      /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
      $item_type = $this->getItemTypePluginForColumn($column, $context);

      if (!empty($limit_item_types) && !in_array($item_type->getPluginId(), $limit_item_types)) {
        $row[] = NULL;
        continue;
      }

      if ($item_type->checkFilter() === FALSE) {
        // Leave early.
        return FALSE;
      }

      $progress_group = NULL;
      if ($item_type->getColumnType() == 'percentage') {
        $progress_group = 'percentage';
      }
      elseif ($item_type->getColumnType() == 'amount') {
        $progress_group = 'amount-' . $key;
      }

      if ($grouped_attachments && $item_type->getPluginId() == 'entity_name') {
        // For grouped attachments, we want to replace the entity name with the
        // attachment desciption.
        $row[] = [
          'data' => [
            '#markup' => Markup::create('<span class="name">' . $attachment->description . '</span>'),
          ],
          'data-value' => $attachment->description,
          'data-raw-value' => $attachment->description,
          'data-sort-type' => $item_type::SORT_TYPE,
          'data-column-type' => $item_type->getColumnType(),
          'data-content' => $item_type->getLabel(),
          'class' => array_merge($item_type->getClasses(), ['subrow']),
        ];
      }
      else {
        // Then add the value to the row.
        $cell = $item_type->getTableCell();
        $cell['data-progress-group'] = $progress_group;
        $row[] = $cell;
      }
    }
    return $row;
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'base' => [
        'include_non_caseloads' => FALSE,
        'include_unpublished_clusters' => FALSE,
        'prototype_id' => NULL,
      ],
      'table' => [
        'columns' => [],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform($is_new = FALSE) {
    $conf = $this->getBlockConfig();
    if (!empty($conf['base']) && !empty($conf['table'])) {
      return 'table';
    }
    return 'base';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitleSubform() {
    return 'base';
  }

  /**
   * Form callback for the base settings form.
   */
  public function baseForm(array $form, FormStateInterface $form_state) {

    $prototype_options = $this->getUniquePrototypeOptions();
    $form['prototype_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Attachment prototype'),
      '#options' => $prototype_options,
      '#description' => $this->t('Select the type of attachments that should be displayed.'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'prototype_id'),
    ];
    if (count($prototype_options) == 1) {
      $form['prototype_id']['#access'] = FALSE;
      $form['prototype_id']['#value'] = array_key_first($prototype_options);
    }

    $form['include_non_caseloads'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include clusters without caseloads'),
      '#description' => $this->t('Check this if you want that clusters without caseload attachments are to be included in the table.'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'include_non_caseloads'),
    ];

    $form['include_unpublished_clusters'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include unpublished clusters'),
      '#description' => $this->t('Check this if you want that unpublished clusters are to be included in the table.'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'include_unpublished_clusters'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function tableForm(array $form, FormStateInterface $form_state) {
    $default_value = $this->getDefaultFormValueFromFormState($form_state, 'columns');
    if (empty($default_value)) {
      $default_value = [
        [
          'item_type' => 'entity_name',
          'config' => [
            'label' => $this->t('Cluster'),
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
      'type' => 'caseload',
    ]);

    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentSearchQuery $query */
    $query = $this->getQueryHandler('attachment_search');
    return $query->getAttachmentsByObject('governingEntity', $entity_ids, $filter);
  }

  /**
   * Get unique prototype options for the available attachments of this block.
   *
   * @return array
   *   An array of prototype names, keyed by the prototype id.
   */
  private function getUniquePrototypeOptions() {
    $prototypes = $this->getUniquePrototypes();
    return array_map(function ($prototype) {
      return $prototype->name;
    }, $prototypes);
  }

  /**
   * Group the given attachments by the governing entity id.
   *
   * @param array $attachments
   *   An array of attachment objects as returned by
   *   AttachmentSearchQuery::getAttachmentsByObject().
   *
   * @return array
   *   An array of arrays of attachment objects, keyed by the entity id.
   */
  private function groupAttachmentsByEntityId(array $attachments) {
    $grouped_attachements = [];
    foreach ($attachments as $attachment) {
      $entity_id = $attachment->source->entity_id;
      if (!array_key_exists($entity_id, $grouped_attachements)) {
        $grouped_attachements[$entity_id] = [];
      }
      $grouped_attachements[$entity_id][] = $attachment;
    }

    return $grouped_attachements;
  }

  /**
   * Filter the given set of attachments by the given prototype id.
   *
   * @param object[] $attachments
   *   The attachments to filter.
   * @param int $prototype_id
   *   The prototype id to filter for.
   *
   * @return array
   *   An array of attachment objects that passed the filter.
   */
  private function filterAttachmentsByPrototype(array $attachments, $prototype_id) {
    return array_filter($attachments, function ($attachment) use ($prototype_id) {
      return $attachment->prototype->id == $prototype_id;
    });
  }

  /**
   * Filter attachments with empty description.
   *
   * @param array $grouped_attachments
   *   An array of arrays of attachment objects, keyed by the entity id.
   *
   * @return array
   *   An array of arrays of attachment objects, keyed by the entity id.
   */
  private function filterEmptyDescriptionInGroupedAttachments(array $grouped_attachments) {
    // Filter out attachments with empty description for cases where there are
    // more than 1 caseload attachments per GVE. In that case a group header
    // with the name of the GVE would be added and the attachment description
    // would be used in the group name column.
    foreach ($grouped_attachments as &$attachments) {
      if (!empty($attachments) && count($attachments) > 1) {
        $attachments = array_filter($attachments, function ($attachment) {
          return !empty($attachment->description);
        });
      }
    }
    return array_filter($grouped_attachments);
  }

  /**
   * Load the nodes associated to the entities.
   *
   * @param array $entities
   *   The entity objects.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface[]
   *   An array of base objects.
   */
  private function loadBaseObjectsForEntities(array $entities) {
    $entity_ids = array_map(function ($entity) {
      return $entity->id;
    }, $entities);

    return BaseObjectHelper::getBaseObjectsFromOriginalIds($entity_ids, 'governing_entity');
  }

  /**
   * Get the first entity node for column configuration.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface
   *   The first entity object available.
   */
  private function getFirstEntityObject() {
    $entities = $this->getEntityObjects();
    if (empty($entities)) {
      return NULL;
    }
    $entity = reset($entities);
    $entity_objects = $this->loadBaseObjectsForEntities([$entity]);
    return !empty($entity_objects) ? reset($entity_objects) : NULL;
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
      'base_object' => $this->getFirstEntityObject(),
      'context_node' => $this->getFirstEntityObject(),
      'attachment_prototype' => $this->getAttachmentPrototype(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedItemTypes() {
    $item_types = [
      'entity_name' => [],
      'data_point' => [
        'label' => $this->t('Data point'),
        'attachment_prototype' => $this->getAttachmentPrototype(),
        'disaggregation_modal' => TRUE,
        'handle_empty_data' => TRUE,
        'select_monitoring_period' => TRUE,
      ],
      'spark_line_chart' => [],
      'monitoring_period' => [],
    ];
    return $item_types;
  }

  /**
   * {@inheritdoc}
   */
  public function buildDownloadData() {
    return $this->buildTableData();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigErrors() {
    $prototype = $this->getAttachmentPrototype();
    $prototype_options = $this->getUniquePrototypeOptions();
    $errors = [];
    if (!$prototype && (empty($prototype_options) || count($prototype_options) > 1)) {
      $errors[] = $this->t('Attachment prototype: Invalid prototype or multiple prototypes available in the new plan context');
    }
    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function fixConfigErrors() {
    $conf = $this->getBlockConfig();
    $original_prototype_id = $conf['base']['prototype_id'] ?? NULL;
    $prototype_options = $this->getUniquePrototypeOptions();
    if (count($prototype_options) == 1) {
      $conf['base']['prototype_id'] = array_key_first($prototype_options);
    }
    if ($original_prototype_id && !empty($conf['base']['prototype_id'])) {
      $new_prototype_id = $conf['base']['prototype_id'];
      /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentPrototypeQuery $query */
      $query = $this->endpointQueryManager->createInstance('attachment_prototype_query');
      $original_prototype = $query->getPrototypeById($original_prototype_id);
      $new_prototype = $query->getPrototypeById($new_prototype_id);
      foreach ($conf['table']['columns'] as &$column) {
        if ($column['item_type'] == 'data_point') {
          $data_points = &$column['config']['data_point']['data_points'];
          $data_points[0]['index'] = AttachmentMatcher::matchDataPointOnAttachmentPrototypes($data_points[0]['index'], $original_prototype, $new_prototype);
          if ($column['config']['data_point']['processing'] != 'single') {
            $data_points[1]['index'] = AttachmentMatcher::matchDataPointOnAttachmentPrototypes($data_points[1]['index'], $original_prototype, $new_prototype);
          }
        }
        if ($column['item_type'] == 'spark_line_chart') {
          $column['config']['data_point'] = AttachmentMatcher::matchDataPointOnAttachmentPrototypes($column['config']['data_point'], $original_prototype, $new_prototype);
          if ($column['config']['show_baseline']) {
            $column['config']['baseline'] = AttachmentMatcher::matchDataPointOnAttachmentPrototypes($column['config']['baseline'], $original_prototype, $new_prototype);
          }
        }
      }
    }
    $this->setBlockConfig($conf);
  }

}
