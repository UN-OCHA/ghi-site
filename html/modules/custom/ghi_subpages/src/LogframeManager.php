<?php

namespace Drupal\ghi_subpages;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_base_objects\Entity\BaseObjectAwareEntityInterface;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_blocks\Traits\AttachmentTableTrait;
use Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\LogframeSubpage;
use Drupal\hpc_api\Query\EndpointQueryManager;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\Plugin\SectionStorage\DefaultsSectionStorage;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Logframe manager service class.
 */
class LogframeManager implements ContainerInjectionInterface {

  use StringTranslationTrait;
  use LayoutEntityHelperTrait;
  use AttachmentTableTrait;
  use DependencySerializationTrait;

  /**
   * Allow to exclude specific API entity types.
   */
  const EXCLUDE_ENTITY_TYPES = ['CQ'];

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The UUID generator service.
   *
   * @var \Drupal\Component\UuidInterface
   */
  protected $uuidGenerator;

  /**
   * The manager class for endpoint query plugins.
   *
   * @var \Drupal\hpc_api\Query\EndpointQueryManager
   */
  protected $endpointQueryManager;

  /**
   * The plugin context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * Layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * Public constructor.
   */
  public function __construct(BlockManagerInterface $block_manager, UuidInterface $uuid, EndpointQueryManager $endpoint_query_manager, ContextHandlerInterface $context_handler, LayoutTempstoreRepositoryInterface $layout_tempstore_repository) {
    $this->blockManager = $block_manager;
    $this->uuidGenerator = $uuid;
    $this->endpointQueryManager = $endpoint_query_manager;
    $this->contextHandler = $context_handler;
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block'),
      $container->get('uuid'),
      $container->get('plugin.manager.endpoint_query_manager'),
      $container->get('context.handler'),
      $container->get('layout_builder.tempstore_repository'),
    );
  }

  /**
   * Setup the logframe page if it's empty.
   *
   * @param \Drupal\ghi_subpages\Entity\LogframeSubpage $node
   *   The logframe node object.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   An optional section storage object.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface
   *   The section storage.
   */
  public function setupLogframePage(LogframeSubpage $node, SectionStorageInterface $section_storage = NULL) {
    if (!$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      return FALSE;
    }

    if ($section_storage === NULL) {
      $section_storage = $this->getSectionStorageForEntity($node);
    }

    $delta = 0;
    $section = $section_storage->getSection($delta);

    // First, make sure we have an overridden section storage.
    if ($section_storage instanceof DefaultsSectionStorage) {
      // Overide the section storage in the tempstore.
      $section_storage = $this->sectionStorageManager()->load('overrides', [
        'entity' => EntityContext::fromEntity($node),
      ]);
      $section_storage->appendSection($section);
    }

    // Clear the current storage.
    if (!empty($section->getComponents())) {
      foreach ($section->getComponents() as $component) {
        $section->removeComponent($component->getUuid());
      }
    }

    // Add the headline figures component.
    if ($node->getParentNode() instanceof SectionNodeInterface) {
      $component = $this->buildHeadlineFiguresComponent($node);
      $section->appendComponent($component);
    }

    // Get entity types.
    $entity_types = $this->getEntityTypesFromNode($node, TRUE);
    if (empty($entity_types)) {
      return NULL;
    }
    // Build one entity logframe element for each entity type.
    foreach (array_keys($entity_types) as $ref_code) {
      $component = $this->buildEntityLogframeComponent($node, $ref_code);
      if (!$component) {
        continue;
      }
      $section->appendComponent($component);
    }

    // Add the cluster logframe links.
    if ($node->getParentNode() instanceof SectionNodeInterface) {
      $component = $this->buildClusterLogframeLinksComponent($node);
      $section->appendComponent($component);
    }

    return $section_storage;
  }

  /**
   * Build a headline figures section component for the given logframe node.
   *
   * @param \Drupal\ghi_subpages\Entity\LogframeSubpage $node
   *   The logframe node to which the component should be added.
   *
   * @return \Drupal\layout_builder\SectionComponent
   *   A section component object.
   */
  private function buildHeadlineFiguresComponent(LogframeSubpage $node) {
    $plan = $node->getParentBaseNode()->getBaseObject();
    $prototype = $this->getPlanPrototype($plan);

    $definition = $this->blockManager->getDefinition('plan_headline_figures', FALSE);
    $context_mapping = $this->buildContextMappingForBlock($definition, $node);

    $configuration = [
      'hpc' => [
        'key_figures' => [
          'items' => [
            [
              'id' => 1,
              'item_type' => 'item_group',
              'config' => [
                'label' => 'Plan Entities',
                'id' => 'plan_entities',
              ],
              'weight' => '0',
              'pid' => NULL,
            ],
          ],
        ],
      ],
    ];

    // Assemble the key figures as entity counter items for all plan entity
    // types.
    foreach ($prototype->getEntityPrototypes() as $entity_prototype) {
      if ($entity_prototype->type != 'PE') {
        // Only show entity counter items for plan entities.
        continue;
      }
      $configuration['hpc']['key_figures']['items'][] = [
        'id' => count($configuration['hpc']['key_figures']['items']),
        'item_type' => 'entity_counter',
        'config' => [
          'entity_type' => 'plan',
          'entity_prototype' => $entity_prototype->id(),
          'label' => '',
          'value_preview' => '',
        ],
        'weight' => 0,
        'pid' => 1,
      ];
    }
    // Append a new component.
    $config = array_filter([
      'id' => $definition['id'],
      'provider' => $definition['provider'],
      'data_sources' => $definition['data_sources'] ?? NULL,
      'label' => '<none>',
      'label_display' => TRUE,
    ]) + $context_mapping;
    $config += $configuration;
    return new SectionComponent($this->uuidGenerator->generate(), 'content', $config);
  }

  /**
   * Build an entity logframe section component for the given logframe node.
   *
   * @param \Drupal\ghi_subpages\Entity\LogframeSubpage $node
   *   The logframe node to which the component should be added.
   * @param string $ref_code
   *   The ref code for the component.
   *
   * @return \Drupal\layout_builder\SectionComponent
   *   A section component object.
   */
  private function buildEntityLogframeComponent(LogframeSubpage $node, $ref_code) {
    $entities = $this->getPlanEntities($node, $ref_code);
    if (empty($entities)) {
      return NULL;
    }
    $definition = $this->blockManager->getDefinition('plan_entity_logframe', FALSE);
    $context_mapping = $this->buildContextMappingForBlock($definition, $node);

    /** @var \Drupal\ghi_plans\Entity\Plan $plan */
    $plan = $node->getParentBaseNode()->getBaseObject();

    // Set the basic configuration for a single plan entity logframe element.
    $configuration = [
      'hpc' => [
        'entities' => [
          'entity_ids' => array_fill_keys(array_keys($entities), 0),
          'entity_ref_code' => $ref_code,
          'id_type' => 'composed_reference',
          'sort' => TRUE,
          'sort_column' => 'id_ASC',
        ],
        'tables' => [
          'attachment_tables' => [],
        ],
        'display' => [
          'label' => '',
          'label_display' => TRUE,
        ],
      ],
    ];

    // See if there are attachment tables to be added.
    $attachment_prototypes = $this->getAttachmentPrototypesForEntityRefCode($node, $ref_code);
    uasort($attachment_prototypes, function ($a, $b) {
      return strnatcasecmp($a->type, $b->type);
    });
    foreach (array_values($attachment_prototypes) as $id => $attachment_prototype) {
      $table_config = [
        'id' => $id,
        'item_type' => 'attachment_table',
        'config' => [
          'attachment_prototype' => $attachment_prototype->id(),
        ],
      ];
      $columns = [];
      if ($attachment_prototype->isIndicator()) {
        $columns = $this->buildIndicatorColumns($attachment_prototype, $plan);
      }
      else {
        $columns = $this->buildCaseloadColumns($attachment_prototype, $plan);
      }
      $table_config['config']['table_form']['columns'] = $columns;
      $configuration['hpc']['tables']['attachment_tables'][] = $table_config;
    }
    // Append a new component.
    $config = array_filter([
      'id' => $definition['id'],
      'provider' => $definition['provider'],
      'data_sources' => $definition['data_sources'] ?? NULL,
      'label' => '<none>',
      'label_display' => TRUE,
    ]) + $context_mapping;
    $config += $configuration;
    return new SectionComponent($this->uuidGenerator->generate(), 'content', $config);
  }

  /**
   * Build a cluster logframe links section component for the logframe node.
   *
   * @param \Drupal\ghi_subpages\Entity\LogframeSubpage $node
   *   The logframe node to which the component should be added.
   *
   * @return \Drupal\layout_builder\SectionComponent
   *   A section component object.
   */
  private function buildClusterLogframeLinksComponent(LogframeSubpage $node) {
    $definition = $this->blockManager->getDefinition('plan_cluster_logframe_links', FALSE);
    $context_mapping = $this->buildContextMappingForBlock($definition, $node);
    /** @var \Drupal\ghi_plans\Entity\Plan $plan */
    $plan = $node->getParentBaseNode()->getBaseObject();
    $langcode = $plan->getPlanLanguage();

    $title_map = [
      Plan::CLUSTER_TYPE_CLUSTER => $this->t('Cluster frameworks', [], ['langcode' => $langcode]),
      Plan::CLUSTER_TYPE_SECTOR => $this->t('Sector frameworks', [], ['langcode' => $langcode]),
    ];

    // Append a new component.
    $config = array_filter([
      'id' => $definition['id'],
      'provider' => $definition['provider'],
      'label' => $title_map[$plan->getPlanClusterType()],
      'label_display' => TRUE,
      'hpc' => [
        'label' => NULL,
        'label_display' => TRUE,
      ],
    ]) + $context_mapping;

    return new SectionComponent($this->uuidGenerator->generate(), 'content', $config);
  }

  /**
   * Build the context mapping for the given plugin definition and node object.
   *
   * @param array $definition
   *   The plugin definition.
   * @param \Drupal\node\NodeInterface $node
   *   The node object to which the plugin should be added to.
   *
   * @return array
   *   An array with context mapping information.
   */
  private function buildContextMappingForBlock($definition, $node) {
    $context_mapping = [
      'context_mapping' => array_intersect_key([
        'node' => 'layout_builder.entity',
      ], $definition['context_definitions']),
    ];
    // And make sure that base objects are mapped too.
    $base_objects = BaseObjectHelper::getBaseObjectsFromNode($node);
    foreach ($base_objects as $_base_object) {
      $contexts = [
        EntityContext::fromEntity($_base_object),
      ];
      foreach ($definition['context_definitions'] as $context_key => $context_definition) {
        $matching_contexts = $this->contextHandler->getMatchingContexts($contexts, $context_definition);
        if (empty($matching_contexts)) {
          continue;
        }
        $context_mapping['context_mapping'][$context_key] = $_base_object->getUniqueIdentifier();
      }
    }
    return $context_mapping;
  }

  /**
   * Build caseload columns for an attachment prototype.
   *
   * @param \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype $attachment_prototype
   *   The attachment prototype.
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan object that the attachment prototype belongs to.
   *
   * @return array
   *   Configuration array for table columns compatible with configuration
   *   container items.
   */
  private function buildCaseloadColumns(AttachmentPrototype $attachment_prototype, Plan $plan) {
    $columns = [];
    // Setup the columns.
    $columns[] = [
      'item_type' => 'attachment_label',
      'config' => [
        'label' => $this->t('Caseload', [], ['langcode' => $plan->getPlanLanguage()]),
      ],
      'id' => 0,
    ];
    // Take the in need and target metrics and the first measurement.
    $field_types = $attachment_prototype->getFieldTypes();
    $in_need = array_search('in_need', $field_types);
    $target = array_search('target', $field_types);
    $measure_fields = $attachment_prototype->getMeasurementMetricFields();
    $measure_keys = array_keys($measure_fields);
    $measure = count($measure_keys) ? ($measure_keys[1] ?? end($measure_keys)) : NULL;
    $available_fields = [
      $in_need,
      $target,
      $measure,
    ];
    $available_fields = array_filter($available_fields, function ($field) {
      return $field !== NULL;
    });
    foreach ($available_fields as $index) {
      $columns[] = [
        'id' => count($columns),
        'item_type' => 'data_point',
        'config' => [
          'label' => '',
          'data_point' => [
            'processing' => 'single',
            'calculation' => 'addition',
            'data_points' => [
              0 => [
                'index' => $index,
                'monitoring_period' => 'latest',
              ],
              1 => [
                'index' => '0',
                'monitoring_period' => 'latest',
              ],
            ],
            'formatting' => 'auto',
            'widget' => 'none',
          ],
        ],
      ];
    }
    if ($measure && $target) {
      $columns[] = [
        'id' => count($columns),
        'item_type' => 'data_point',
        'config' => [
          'label' => $attachment_prototype->getDefaultFieldLabel($index, $plan->getPlanLanguage()) . ' %',
          'data_point' => [
            'processing' => 'calculated',
            'calculation' => 'percentage',
            'data_points' => [
              0 => [
                'index' => $measure,
                'monitoring_period' => 'latest',
              ],
              1 => [
                'index' => $target,
                'monitoring_period' => 'latest',
              ],
            ],
            'formatting' => 'auto',
            'widget' => 'none',
          ],
        ],
      ];
    }
    return $columns;
  }

  /**
   * Build indicator columns for an attachment prototype.
   *
   * @param \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype $attachment_prototype
   *   The attachment prototype.
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan object that the attachment prototype belongs to.
   *
   * @return array
   *   Configuration array for table columns compatible with configuration
   *   container items.
   */
  private function buildIndicatorColumns(AttachmentPrototype $attachment_prototype, Plan $plan) {

    $columns = [];
    // Setup the columns.
    $columns[] = [
      'item_type' => 'attachment_label',
      'config' => [
        'label' => $this->t('Indicator', [], ['langcode' => $plan->getPlanLanguage()]),
      ],
      'id' => count($columns),
    ];
    $columns[] = [
      'item_type' => 'attachment_unit',
      'config' => [
        'label' => $this->t('Unit', [], ['langcode' => $plan->getPlanLanguage()]),
      ],
      'id' => count($columns),
    ];
    // Take the first metric of type target and the last measurement.
    $field_types = $attachment_prototype->getFieldTypes();
    $target = array_search('target', $field_types);
    $measure = array_search('measure', array_reverse($field_types, TRUE));
    $available_fields = [
      $target,
      $measure,
    ];
    $available_fields = array_filter($available_fields, function ($field) {
      return $field !== NULL;
    });
    foreach ($available_fields as $index) {
      $columns[] = [
        'id' => count($columns),
        'item_type' => 'data_point',
        'config' => [
          'label' => '',
          'data_point' => [
            'processing' => 'single',
            'calculation' => 'addition',
            'data_points' => [
              0 => [
                'index' => $index,
                'use_calculation_method' => '1',
              ],
              1 => [
                'index' => '0',
                'use_calculation_method' => '1',
              ],
            ],
            'formatting' => 'auto',
            'widget' => 'none',
          ],
        ],
      ];
    }
    if ($measure) {
      $columns[] = [
        'id' => count($columns),
        'item_type' => 'spark_line_chart',
        'config' => [
          'label' => $this->t('Progress', [], ['langcode' => $plan->getPlanLanguage()]),
          'data_point' => $measure,
          'baseline' => $target,
          'use_calculation_method' => FALSE,
          'monitoring_periods' => [],
          'include_latest_period' => 0,
          'show_baseline' => 0,
        ],
      ];
    }
    return $columns;
  }

  /**
   * Get the entity types from the given logframe node.
   *
   * @param \Drupal\ghi_subpages\Entity\LogframeSubpage $node
   *   The logframe node object.
   *
   * @return array
   *   An array of entity types, keyed by ref code, value is the plural name.
   */
  public function getEntityTypesFromNode(LogframeSubpage $node) {
    $section = $node->getParentBaseNode();
    if (!$section instanceof SectionNodeInterface) {
      return NULL;
    }
    $plan_object = $section->getBaseObject();
    if (!$plan_object instanceof Plan) {
      return NULL;
    }
    $entity_types = $this->getEntityTypesFromPlanObject($plan_object);
    $entity_types = array_filter($entity_types, function ($ref_code) use ($node) {
      return !empty($this->getPlanEntities($node, $ref_code));
    }, ARRAY_FILTER_USE_KEY);

    // Filter by parent if available. This is to support the use case of plan
    // cluster specific logframes as semi-automatic standard subpage for plan
    // clusters.
    $parent = $node->getParentNode();
    if (!$parent instanceof SectionNodeInterface && $parent instanceof BaseObjectAwareEntityInterface) {
      $context_base_object = $parent->getBaseObject();
      $ref_codes = $context_base_object?->getSourceObject()?->getValidRefCodes() ?? NULL;
      if ($ref_codes) {
        $entity_types = array_intersect_key($entity_types, array_flip($ref_codes));
      }
    }
    if ($parent instanceof SectionNodeInterface) {
      // Logframes on plan level should only include the plan entities above
      // the first governing entity.
      $ref_codes = ['PL', 'SO', 'SP'];
      $entity_types = array_intersect_key($entity_types, array_flip($ref_codes));
    }

    // Always filter out Humanitarian Conditions.
    $ref_codes = ['HC'];
    $entity_types = array_diff_key($entity_types, array_flip($ref_codes));

    return $entity_types;
  }

  /**
   * Get the plan prototype.
   *
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan base object.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanPrototype
   *   The prototype for the plan.
   */
  private function getPlanPrototype(Plan $plan) {
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanPrototypeQuery $prototype_query */
    $prototype_query = $this->endpointQueryManager->createInstance('plan_prototype_query');
    return $prototype_query->getPrototype($plan->getSourceId());
  }

  /**
   * Get the entity types from the given plan object.
   *
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan entity object.
   *
   * @return array
   *   An array of entity types, keyed by ref code, value is the plural name.
   */
  public function getEntityTypesFromPlanObject(Plan $plan) {
    $prototype = $this->getPlanPrototype($plan);

    $entity_types = [
      'PL' => (string) $this->t('Plan'),
    ];
    foreach ($prototype->getEntityPrototypes() as $entity_prototype) {
      $ref_code = $entity_prototype->getRefCode();
      $entity_types[$ref_code] = $entity_prototype->getPluralName();
    }
    if (!empty(self::EXCLUDE_ENTITY_TYPES)) {
      $entity_types = array_diff_key($entity_types, array_flip(self::EXCLUDE_ENTITY_TYPES));
    }
    return $entity_types;
  }

  /**
   * Get the plan entities for the given logframe node.
   *
   * @param \Drupal\ghi_subpages\Entity\LogframeSubpage $node
   *   The logframe node object.
   * @param string $ref_code
   *   An optional entity ref code to restrict the retrieved entities.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[]
   *   An array of plan entities.
   */
  private function getPlanEntities(LogframeSubpage $node, $ref_code = NULL) {
    $section = $node->getParentBaseNode();
    if (!$section instanceof SectionNodeInterface) {
      return NULL;
    }
    $filter = NULL;
    if ($ref_code) {
      $filter = ['ref_code' => $ref_code];
    }

    $entities = [];
    $base_object = $section->getBaseObject();
    if ($base_object instanceof Plan && $ref_code == 'PL') {
      /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\EntityQuery $query */
      $query = $this->endpointQueryManager->createInstance('entity_query');
      $plan_data = $query->getEntity('plan', $base_object->getSourceId());
      if ($plan_data) {
        $entities = [
          $plan_data->id() => $plan_data,
        ];
      }
    }

    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery $query */
    $query = $this->endpointQueryManager->createInstance('plan_entities_query');
    $query->setPlaceholder('plan_id', $base_object->getSourceId());
    $entities = $entities + ($query->getPlanEntities($base_object, NULL, $filter) ?? []);
    // This should give us only PlanEntity objects, but let's make sure.
    $entities = is_array($entities) ? array_filter($entities, function ($entity) {
      return $entity instanceof PlanEntityInterface;
    }) : [];
    return $entities;
  }

  /**
   * Get the available attachment prototypes for the current plan context.
   *
   * @param \Drupal\ghi_subpages\Entity\LogframeSubpage $node
   *   The logframe node object.
   * @param string $ref_code
   *   The entity ref code.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype[]
   *   An array of attachment prototypes.
   */
  private function getAttachmentPrototypesForEntityRefCode(LogframeSubpage $node, $ref_code) {
    $section = $node->getParentBaseNode();
    if (!$section instanceof SectionNodeInterface || !$section->getBaseObject() instanceof Plan) {
      return [];
    }

    $plan_id = $section->getBaseObject()->getSourceId();
    if (!$plan_id) {
      return [];
    }
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanAttachmentPrototypeQuery $query */
    $query = $this->endpointQueryManager->createInstance('plan_attachment_prototype_query');
    $attachment_prototypes = $query->getDataPrototypesForPlan($plan_id);
    return $this->filterAttachmentPrototypesByEntityRefCodes($attachment_prototypes, [$ref_code]);
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
    // Filter out non-data attachments.
    $attachments = array_filter($attachments, function ($attachment) use ($prototype_id) {
      if (!$attachment instanceof DataAttachment) {
        return FALSE;
      }
      if ($prototype_id && $prototype_id == $attachment->getPrototype()->id()) {
        return FALSE;
      }
      return TRUE;
    });
    return $attachments;
  }

}
