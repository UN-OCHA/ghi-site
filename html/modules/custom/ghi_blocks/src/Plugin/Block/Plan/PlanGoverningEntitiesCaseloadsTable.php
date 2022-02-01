<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\ghi_element_sync\SyncableBlockInterface;
use Drupal\node\NodeInterface;

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
 *  },
 *  default_title = @Translation("Cluster caseloads"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *    "plan" = @ContextDefinition("entity:base_object", label = @Translation("Plan"), constraints = { "Bundle": "plan" })
 *   }
 * )
 */
class PlanGoverningEntitiesCaseloadsTable extends GHIBlockBase implements ConfigurableTableBlockInterface, MultiStepFormBlockInterface, SyncableBlockInterface {

  use ConfigurationContainerTrait;

  /**
   * {@inheritdoc}
   */
  public static function mapConfig($config, NodeInterface $node, $element_type, $dry_run = FALSE) {
    $columns = [];
    // Define a transition map.
    $transition_map = [
      'governing_entity_name' => [
        'target' => 'entity_name',
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
      if (is_object($value) && property_exists($value, 'cluster_restrict') && property_exists($value, 'cluster_tag')) {
        $item['config']['cluster_restrict'] = [
          'type' => $value->cluster_restrict,
          'tag' => $value->cluster_tag,
        ];
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

        case 'monitoring_period':
          $item['config']['display_type'] = $value->display_type;
          break;

        default:
          break;
      }
      $columns[] = $item;
    }
    return [
      'label' => property_exists($config, 'widget_title') ? $config->widget_title : NULL,
      'label_display' => TRUE,
      'hpc' => [
        'base' => [
          'include_non_caseloads' => property_exists($config, 'include_non_caseloads') ? $config->include_non_caseloads : FALSE,
          'include_unpublished_clusters' => property_exists($config, 'include_unpublished_clusters') ? $config->include_unpublished_clusters : FALSE,
        ],
        'table' => [
          'columns' => $columns,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
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
        'data-column-type' => $item_type::ITEM_TYPE,
      ];
    }

    // Get the prototype.
    $prototype = $this->getAttachmentPrototype();

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
      return strnatcasecmp($a->name, $b->name);
    });

    $rows = [];
    foreach ($entities as $entity) {
      if (!array_key_exists($entity->id, $objects)) {
        continue;
      }
      $base_object = $objects[$entity->id];
      $section = !$base_object->needsYear() ? $this->sectionManager->loadSectionForBaseObject($base_object) : NULL;

      // Clusters are displayed if they are either published, or the element is
      // configured to also display unpublished clusters.
      if ($section && !$section->access('view') && empty($conf['base']['include_unpublished_clusters'])) {
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
      $context['context_node'] = $base_object && $base_object->bundle() != 'plan' ? $base_object : NULL;
      $context['section_node'] = $section;
      $context['entity'] = $entity;

      // Add another row as a group header for clusters with multiple plan
      // caseloads.
      if (count($attachments) > 1) {
        $rows[] = $this->buildTableRow($columns, $context, ['entity_name']);
      }

      foreach ($attachments as $attachment) {
        $context['attachment'] = $attachment;
        $rows[] = $this->buildTableRow($columns, $context, [], count($attachments) > 1);
      }
    }
    $rows = array_filter($rows);

    if (empty($rows)) {
      return;
    }

    return [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
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
    foreach ($columns as $column) {

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

      if ($grouped_attachments && $item_type->getPluginId() == 'entity_name') {
        // For grouped attachments, we want to replace the entity name with the
        // attachment desciption.
        $row[] = [
          'data' => [
            '#markup' => Markup::create('<span class="name">' . $attachment->description . '</span>'),
          ],
          'data-value' => $attachment->description,
          'data-sort-value' => $attachment->description,
          'data-sort-type' => $item_type::SORT_TYPE,
          'data-column-type' => $item_type::ITEM_TYPE,
          'class' => array_merge($item_type->getClasses(), ['subrow']),
        ];
      }
      else {
        // Then add the value to the row.
        $row[] = [
          'data' => $item_type->getRenderArray(),
          'data-value' => $item_type->getValue(),
          'data-sort-value' => $item_type->getSortableValue(),
          'data-sort-type' => $item_type::SORT_TYPE,
          'data-column-type' => $item_type::ITEM_TYPE,
          'class' => $item_type->getClasses(),
        ];
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
  public function getSubforms() {
    return [
      'base' => [
        'title' => $this->t('Base settings'),
        'callback' => 'baseForm',
        'base_form' => TRUE,
      ],
      'table' => [
        'title' => $this->t('Table columns'),
        'callback' => 'tableForm',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform() {
    $conf = $this->getBlockConfig();
    if (!empty($conf['table']) && !empty($conf['table'])) {
      return 'table';
    }
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
   * Get the attachment prototype to use for the current block instance.
   *
   * @return object
   *   The attachment prototype object..
   */
  private function getAttachmentPrototype() {
    $conf = $this->getBlockConfig();
    $prototype_id = $conf['base']['prototype_id'];
    $prototypes = $this->getUniquePrototypes();
    return array_key_exists($prototype_id, $prototypes) ? $prototypes[$prototype_id] : reset($prototypes);
  }

  /**
   * Get all governing entity objects for the current block instance.
   *
   * @return object[]
   *   An array of entity objects, aka clusters.
   */
  private function getEntityObjects() {
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery $query */
    $query = $this->getQueryHandler('entities');
    return $query->getPlanEntities($this->getPageNode(), 'governing');
  }

  /**
   * Get all attachment objects for the current block instance.
   *
   * @return object[]
   *   An array of attachment objects.
   */
  private function getAttachments() {
    $entities = $this->getEntityObjects();
    if (empty($entities)) {
      return NULL;
    }
    return $this->getAttachmentsForEntities($entities);
  }

  /**
   * Get unique prototype options for the available attachments of this block.
   *
   * @return array
   *   An array of prototype names, keyed by the prototype id.
   */
  private function getUniquePrototypes() {
    $attachments = $this->getAttachments() ?? [];
    $prototype_opions = [];
    foreach ($attachments as $attachment) {
      $prototype = $attachment->prototype;
      if (array_key_exists($prototype->id, $prototype_opions)) {
        continue;
      }
      $prototype_opions[$prototype->id] = $prototype;
    }
    return $prototype_opions;
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
   * Get all attachment objects for the given entities.
   *
   * @return object[]
   *   An array of attachment objects.
   */
  private function getAttachmentsForEntities(array $entities, $prototype_id = NULL) {
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
    return $query->getAttachmentsByObject('governingEntity', implode(',', $entity_ids), $filter);
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
   * Get the allowed item types for this element.
   *
   * @return array
   *   An array with the allowed item types, keyed by the plugin id, with the
   *   value being an optional configuration array for the plugin.
   */
  public function getAllowedItemTypes() {
    $item_types = [
      'entity_name' => [],
      'data_point' => [
        'label' => $this->t('Data point'),
        'attachment_prototype' => $this->getAttachmentPrototype(),
      ],
      'monitoring_period' => [],
    ];
    return $item_types;
  }

}
