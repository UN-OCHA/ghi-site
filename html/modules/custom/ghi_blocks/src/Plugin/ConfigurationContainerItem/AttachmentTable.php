<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Interfaces\AttachmentTableInterface;
use Drupal\ghi_blocks\Traits\AttachmentTableTrait;
use Drupal\ghi_form_elements\ConfigurationContainerItemCustomActionsInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerItemCustomActionTrait;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
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
  use ConfigurationContainerItemCustomActionTrait;
  use AttachmentTableTrait;

  /**
   * The manager class for configuration container items.
   *
   * @var \Drupal\ghi_form_elements\ConfigurationContainerItemManager
   */
  protected $configurationContainerItemManager;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configurationContainerItemManager = $container->get('plugin.manager.configuration_container_item_manager');
    $instance->uuidService = $container->get('uuid');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment[] $attachments */
    $attachments = $this->getContextValue('attachments');
    $prototype_id = $this->get('attachment_prototype');

    // Filter to only selected attachments if configured.
    $attachment_ids = array_filter($this->getConfig()['attachment_form']['attachment_ids'] ?? []);
    if (!empty($attachment_ids)) {
      $attachments = array_intersect_key($attachments, $attachment_ids);
    }

    /** @var \Drupal\ghi_plans\ApiObjects\PlanEntityInterface $plan_entity */
    $plan_entity = $this->getContextValue('plan_entity');
    $attachments = $this->filterAttachments($attachments, $prototype_id, $plan_entity);
    $columns = $this->getColumns();

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
    return [
      '#theme' => 'table',
      '#header' => $this->buildTableHeader($columns),
      '#rows' => $rows,
      '#sortable' => TRUE,
      '#progress_groups' => TRUE,
      '#empty' => $this->t('No data found for this table.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomActions() {
    return [
      'table_form' => $this->t('Columns'),
      'attachment_form' => $this->t('Selection'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isValidAction($action) {
    return $this->getAttachmentPrototype() instanceof AttachmentPrototype;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);
    $element['label']['#access'] = FALSE;
    $options = $this->getAttachmentPrototypeOptions();
    $prototype = $this->getAttachmentPrototype();
    $attachment_prototypes = $this->getAttachmentPrototypes();
    $table_columns = $this->getConfig()['table_form']['columns'] ?? [];
    if (empty($options) && !empty($attachment_prototypes)) {
      $element['empty_message'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('There are no attachment prototypes available anymore. All @count attachment prototypes available for the selected entities are already in use.', [
          '@count' => count($attachment_prototypes),
        ]),
      ];
    }
    elseif (empty($options) && empty($attachment_prototypes)) {
      $element['empty_message'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('There are no attachment prototypes available for the currently selected entities.'),
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
   * Get the columns to be used for the table.
   *
   * Either from the given conf array or from the items configuration. This
   * applies a fallback.
   *
   * @param array $conf
   *   An optional configuration array.
   *
   * @return array
   *   An array of column definitions.
   */
  public function getColumns(array $conf = NULL) {
    $conf = $conf ?? ($this->getConfig()['table_form'] ?? []);
    if (empty($conf['columns'])) {
      $prototype = $this->getAttachmentPrototype();
      $conf['columns'] = [
        [
          'item_type' => 'attachment_label',
          'config' => [
            'label' => $prototype?->getName() ?? (string) $this->t('Indicator'),
          ],
        ],
      ];
    }
    return $conf['columns'];
  }

  /**
   * Build the article selection subform.
   */
  public function tableForm($element, FormStateInterface $form_state, $default_values = NULL) {
    $element['columns'] = [
      '#type' => 'configuration_container',
      '#title' => $this->t('Configured table columns for attachment table @label', [
        '@label' => $this->getLabel(),
      ]),
      '#item_type_label' => $this->t('Column'),
      '#parent_type_label' => $this->t('Attachment table'),
      '#default_value' => $this->getColumns($default_values),
      '#allowed_item_types' => $this->getAllowedItemTypes(),
      '#preview' => [
        'columns' => [
          'label' => $this->t('Label'),
        ],
      ],
      '#element_context' => $this->getBlockContext(),
      '#row_filter' => TRUE,
    ];
    return $element;
  }

  /**
   * Build the article selection subform.
   */
  public function attachmentForm($element, FormStateInterface $form_state, $defaults = NULL) {
    /** @var \Drupal\ghi_plans\ApiObjects\PlanEntityInterface[] $entities */
    $entities = $entities ?? $this->getContextValue('entities');
    $attachments = $this->getAttachmentsForEntities($entities);
    $attachment_options = [];
    foreach ($attachments as $attachment) {
      $attachment_options[$attachment->id()] = [
        'id' => $attachment->id(),
        'composed_reference' => $attachment->composed_reference,
        'description' => $attachment->getDescription(),
      ];
    }
    uasort($attachment_options, function ($a, $b) {
      return SortArray::sortByKeyString($a, $b, 'composed_reference');
    });

    $element['attachment_ids_header'] = [
      '#type' => 'markup',
      '#markup' => $this->t('If you do not want to show all attachments in this table, select the ones that should be visible below. If no attachment is selected, all attachments will be shown.'),
      '#prefix' => '<div>',
      '#suffix' => '</div><br />',
    ];

    $element['attachment_ids'] = [
      '#type' => 'tableselect',
      '#header' => [
        'id' => $this->t('ID'),
        'composed_reference' => $this->t('Reference'),
        'description' => $this->t('Description'),
      ],
      '#options' => $attachment_options,
      '#default_value' => !empty($defaults['attachment_ids']) ? array_combine($defaults['attachment_ids'], $defaults['attachment_ids']) : [],
      '#empty' => $this->t('No suitable attachments found.'),
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
    $attachment_prototypes = $this->getAttachmentPrototypes();
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
   * Get the available attachment prototypes.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype[]
   *   An array of attachment prototype objects.
   */
  private function getAttachmentPrototypes() {
    $context = $this->getContext();
    $attachment_prototypes = $context['attachment_prototypes'] ?? [];
    $entity_types = $context['entity_types'] ?? [];
    return $this->filterAttachmentPrototypesByEntityRefCodes($attachment_prototypes, array_keys($entity_types));
  }

  /**
   * Get attachments for the given set of entities.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface[] $entities
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
    $entity_ids = array_map(function (PlanEntityInterface $entity) {
      return $entity->id();
    }, $entities);

    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentSearchQuery $query */
    $query = $this->endpointQueryManager->createInstance('attachment_search_query');
    $attachments = $query->getAttachmentsByObject('planEntity', $entity_ids);
    $attachments = array_merge($attachments, $query->getAttachmentsByObject('governingEntity', $entity_ids));
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
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface $plan_entity
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
      if (!$source_entity instanceof PlanEntityInterface) {
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
   * Callback for the "columns_summary" preview of the item list.
   *
   * @return mixed
   *   The value to show in the configuration container list view under
   *   "column".
   */
  public function getColumnsSummary() {
    if (!$this->getAttachmentPrototype()) {
      return NULL;
    }
    return count($this->getColumns()) ?: $this->t('Not configured');
  }

  /**
   * Callback for the "label" preview of the item list.
   *
   * @return mixed
   *   The value to show in the configuration container list view under
   *   "prototype".
   */
  public function getLabel() {
    $prototype = $this->getAttachmentPrototype();
    return $prototype ? $prototype->getName() : $this->t('Invalid prototype');
  }

  /**
   * Callback for the "prototype" preview of the item list.
   *
   * @return mixed
   *   The value to show in the configuration container list view under
   *   "prototype".
   */
  public function getPrototype() {
    $prototype = $this->getAttachmentPrototype();
    return $prototype ? $prototype->getName() : NULL;
  }

  /**
   * Fake a uuuid for the purpose of using ConfigurationContainerTrait.
   *
   * @return string
   *   The plugin id.
   */
  public function getUuid() {
    return $this->uuidService->generate();
  }

}
