<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Interfaces\AttachmentTableInterface;
use Drupal\ghi_blocks\Traits\AttachmentTableTrait;
use Drupal\ghi_form_elements\ConfigurationContainerItemCustomActionsInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\ghi_plans\ApiObjects\Entities\PlanEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an article collection item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "attachment_table",
 *   label = @Translation("Attachment table"),
 *   description = @Translation("This item allows the creation of attachment tables."),
 * )
 */
class AttachmentTable extends ConfigurationContainerItemPluginBase implements ConfigurationContainerItemCustomActionsInterface, AttachmentTableInterface {

  use ConfigurationContainerTrait;
  use AttachmentTableTrait;

  /**
   * The manager class for configuration container items.
   *
   * @var \Drupal\ghi_form_elements\ConfigurationContainerItemManager
   */
  protected $configurationContainerItemManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configurationContainerItemManager = $container->get('plugin.manager.configuration_container_item_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment[] $attachments */
    $attachments = $this->getContextValue('attachments');
    $prototype_id = $this->get('attachment_prototype');

    /** @var \Drupal\ghi_plans\ApiObjects\Entities\PlanEntity $entity */
    $plan_entity = $this->getContextValue('plan_entity');
    $attachments = $this->filterAttachments($attachments, $prototype_id, $plan_entity);
    $columns = $this->get('table_form')['columns'] ?? [];
    if (empty($columns) || empty($attachments)) {
      return NULL;
    }

    $rows = [];
    $context = $this->getBlockContext();
    foreach ($attachments as $attachment) {
      $context['attachment'] = $attachment;
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
      '#theme' => 'table',
      '#header' => $this->buildTableHeader($columns),
      '#rows' => $rows,
      '#sortable' => TRUE,
      '#progress_groups' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomActions() {
    return [
      'table_form' => $this->t('Table'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);
    $element['label']['#access'] = FALSE;
    $options = $this->getAttachmentPrototypeOptions();
    $prototype = $this->getAttachmentPrototype();
    $table_columns = $this->getConfig()['table_form']['columns'] ?? [];
    if (empty($options)) {
      $element['empty_message'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('There are no attachment prototypes available anymore. All attachment prototypes based on the entities selected for this element are already in use.'),
      ];
    }
    else {
      $element['attachment_prototype'] = [
        '#type' => 'select',
        '#title' => $this->t('Attachment prototype'),
        '#description' => $this->t('Select the attachment prototype to be used for this table. The available prototypes are based on the available attachments in the context of this page element.'),
        '#options' => $options,
        '#default_value' => $prototype?->id(),
        '#disabled' => !empty($table_columns) && $prototype,
      ];
      if (!empty($table_columns) && $prototype) {
        $element['attachment_prototype']['#description'] .= ' ' . $this->t('The attachment prototype cannot be changed anymore because the table has already been configured');
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function canAddNewItem() {
    return !empty($this->getAttachmentPrototypeOptions());
  }

  /**
   * Build the article selection subform.
   */
  public function tableForm($element, FormStateInterface $form_state, $default_values = NULL) {
    if (empty($default_values['columns'])) {
      $default_values['columns'] = [
        [
          'item_type' => 'attachment_label',
          'config' => [
            'label' => (string) $this->t('Indicator'),
          ],
        ],
      ];
    }

    $element['columns'] = [
      '#type' => 'configuration_container',
      '#title' => $this->t('Configured table columns for attachment table @label', [
        '@label' => $this->getLabel(),
      ]),
      '#item_type_label' => $this->t('Column'),
      '#parent_type_label' => $this->t('Attachment table'),
      '#default_value' => $default_values['columns'],
      '#allowed_item_types' => $this->getAllowedItemTypes(),
      '#preview' => [
        'columns' => [
          'label' => $this->t('Label'),
        ],
      ],
      '#element_context' => $this->getContext(),
      '#row_filter' => TRUE,
    ];
    return $element;
  }

  /**
   * Get the attachment prototype options.
   *
   * @return array
   *   An array of id-label pairs for the attachment prototypes.
   */
  private function getAttachmentPrototypeOptions() {
    $attachment_prototypes = $this->getAttachmentPrototypesForEntities();
    $prototype_id = $this->get('attachment_prototype');
    $used_prototypes = $this->getContextValue('used_attachment_prototypes') ?? [];
    $used_prototypes = array_diff($used_prototypes, [$prototype_id]);
    $options = [];
    foreach ($attachment_prototypes as $attachment_prototype) {
      if (in_array($attachment_prototype->id(), $used_prototypes)) {
        continue;
      }
      $type = $attachment_prototype->getTypeLabel();
      if (empty($options[$type])) {
        $options[$type] = [];
      }
      $options[$type][$attachment_prototype->id()] = $attachment_prototype->getName();
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getBlockContext() {
    $prototype_id = $this->get('attachment_prototype');
    $context = $this->getContext();
    $context['attachment_prototype'] = $context['attachment_prototypes'][$prototype_id] ?? NULL;
    return $context;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedItemTypes() {
    $prototype = $this->getAttachmentPrototype();
    $item_types = [
      'attachment_label' => [
        'default_label' => $prototype?->getName() ?? NULL,
      ],
      'attachment_unit' => [],
      'data_point' => [
        'label' => $this->t('Data point'),
        'attachment_prototype' => $prototype,
        'disaggregation_modal' => TRUE,
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
  public function getEntityObjects() {
    return $this->getContextValue('entities') ?? [];
  }

  /**
   * Get the available attachment prototypes for the given entities.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[] $entities
   *   The plan entity objects.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype[]
   *   An array of attachment prototype objects.
   */
  private function getAttachmentPrototypesForEntities(array $entities = NULL) {
    /** @var \Drupal\ghi_plans\ApiObjects\Entities\PlanEntity[] $entities */
    $entities = $entities ?? $this->getContextValue('entities');
    $attachments = $this->getAttachmentsForEntities($entities, $prototype_id = NULL);
    return $this->getUniquePrototypes($attachments);
  }

  /**
   * Get attachments for the given set of entities.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[] $entities
   *   The plan entity objects.
   * @param int $prototype_id
   *   An optional prototype id to filter for.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment[]
   *   An array of data attachments.
   */
  public function getAttachmentsForEntities(array $entities, $prototype_id = NULL) {
    if (empty($entities)) {
      return NULL;
    }
    $entity_ids = array_map(function ($entity) {
      return $entity->id;
    }, $entities);

    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentSearchQuery $query */
    $query = $this->endpointQueryManager->createInstance('attachment_search_query');
    $attachments = $query->getAttachmentsByObject('planEntity', $entity_ids);
    // Filter the attachments.
    return $this->filterAttachments($attachments, $prototype_id);
  }

  /**
   * Filter the given set of attachments.
   *
   * This filters out any non-data attachments, and optionally also filters by
   * attachment prototype and plan entity.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface[] $attachments
   *   The attachments to filter.
   * @param int $prototype_id
   *   An optional prototype id to filter for.
   * @param \Drupal\ghi_plans\ApiObjects\Entities\PlanEntity $plan_entity
   *   An optional plan entity object.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment[]
   *   An array of data attachments.
   */
  private function filterAttachments(array $attachments, $prototype_id = NULL, $plan_entity = NULL) {
    $attachments = array_filter($attachments, function ($attachment) use ($prototype_id, $plan_entity) {
      if (!$attachment instanceof DataAttachment) {
        return FALSE;
      }
      if ($prototype_id && $prototype_id != $attachment->getPrototype()->id()) {
        return FALSE;
      }
      $source_entity = $attachment->getSourceEntity();
      if (!$source_entity instanceof PlanEntity) {
        return FALSE;
      }
      if ($plan_entity && $plan_entity->id() != $source_entity->id()) {
        return FALSE;
      }
      return TRUE;
    });
    return $attachments;
  }

  /**
   * Get the attachment prototype to use for the current element.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype|null
   *   The attachment prototype object.
   */
  public function getAttachmentPrototype($attachments = NULL) {
    $prototype_id = $this->get('attachment_prototype');
    return $prototype_id ? $this->getAttachmentPrototypeById($prototype_id) : NULL;
  }

  /**
   * Get the attachment prototype to use for the current block instance.
   *
   * @param int $prototype_id
   *   The prototype id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype|null
   *   The attachment prototype object.
   */
  public function getAttachmentPrototypeById($prototype_id) {
    $attachment_prototypes = $this->getBlockContext()['attachment_prototypes'] ?? [];
    return $attachment_prototypes[$prototype_id] ?? NULL;
  }

  /**
   * Callback for the "column" preview of the configuration container list.
   *
   * @return mixed
   *   The value to show in the configuration container list view under
   *   "column".
   */
  public function getColumns() {
    $conf = $this->config;
    $columns = $this->getConfiguredItems($conf['table_form']['columns']);
    $labels = [];
    foreach ($columns as $column) {
      /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
      $item_type = $this->getItemTypePluginForColumn($column);
      if (!$item_type) {
        continue;
      }
      $labels[] = $item_type->getLabel();
    }
    return count($labels) ?: $this->t('Not configured');
  }

  /**
   * Callback for the "prototype" preview of the configuration container list.
   *
   * @return mixed
   *   The value to show in the configuration container list view under
   *   "prototype".
   */
  public function getLabel() {
    $prototype = $this->getAttachmentPrototype();
    return $prototype ? $prototype->getName() : NULL;
  }

  /**
   * Get a uuuid for the purpose of using ConfigurationContainerTrait.
   *
   * @return string
   *   The plugin id.
   */
  public function getUuid() {
    return $this->getPluginId();
  }

}
