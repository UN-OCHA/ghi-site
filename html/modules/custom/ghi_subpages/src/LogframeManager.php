<?php

namespace Drupal\ghi_subpages;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_blocks\Traits\AttachmentTableTrait;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\ghi_plans\ApiObjects\Entities\PlanEntity;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_sections\Entity\Section;
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

  use LayoutEntityHelperTrait;
  use AttachmentTableTrait;

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

    // Get entity types.
    $entity_types = $this->getEntityTypesFromNode($node);
    foreach ($entity_types as $ref_code => $name) {
      $entities = $this->getPlanEntities($node, $ref_code);
      $definition = $this->blockManager->getDefinition('plan_entity_logframe', FALSE);
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
        ],
      ];

      // See if there are attachment tables to be added.
      $attachment_prototypes = $this->getAttachmentPrototypes($node, $entities);
      foreach (array_values($attachment_prototypes) as $id => $attachment_prototype) {
        $table_config = [
          'id' => $id,
          'item_type' => 'attachment_table',
          'config' => [
            'attachment_prototype' => $attachment_prototype->id(),
          ],
        ];
        // Setup the columns.
        $columns = [];
        $columns[] = [
          'item_type' => 'attachment_label',
          'config' => [
            'label' => $attachment_prototype->getName(),
          ],
          'id' => 0,
        ];
        $goal_fields = $attachment_prototype->getGoalMetricFields();
        $measurement_fields = $attachment_prototype->getMeasurementMetricFields();
        // Just take the last goal metric and the first measurement metric for
        // the moment.
        $fields = array_filter([
          array_key_last($goal_fields) => end($goal_fields),
          array_key_first($measurement_fields) => reset($measurement_fields),
        ]);
        foreach ($fields as $index => $field) {
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
      $component = new SectionComponent($this->uuidGenerator->generate(), 'content', $config);
      $section->appendComponent($component);
    }

    return $section_storage;
  }

  /**
   * Get the entity types from the given logframe node.
   *
   * @param \Drupal\ghi_subpages\Entity\LogframeSubpage $node
   *   The logframe node object.
   *
   * @return array
   *   An array of entity ref codes, keyed by ref code, value is the name.
   */
  public function getEntityTypesFromNode(LogframeSubpage $node) {
    $section = $node->getParentNode();
    if (!$section instanceof Section) {
      return NULL;
    }
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery $query */
    $query = $this->endpointQueryManager->createInstance('plan_entities_query');
    $query->setPlaceholder('plan_id', $section->getBaseObject()->getSourceId());
    $entities = $this->getPlanEntities($node);
    return $query->getEntityRefCodeOptions($entities) ?? [];
  }

  /**
   * Get the plan entities for the given logframe node.
   *
   * @param \Drupal\ghi_subpages\Entity\LogframeSubpage $node
   *   The logframe node object.
   * @param string $ref_code
   *   An optional entity ref code to restrict the retrieved entities.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\PlanEntity[]
   *   An array of plan entities.
   */
  private function getPlanEntities(LogframeSubpage $node, $ref_code = NULL) {
    $section = $node->getParentNode();
    if (!$section instanceof Section) {
      return NULL;
    }
    $filter = NULL;
    if ($ref_code) {
      $filter = ['ref_code' => $ref_code];
    }
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery $query */
    $query = $this->endpointQueryManager->createInstance('plan_entities_query');
    $query->setPlaceholder('plan_id', $section->getBaseObject()->getSourceId());
    $entities = $query->getPlanEntities($section->getBaseObject(), 'plan', $filter) ?? [];
    // This should give us only PlanEntity objects, but let's make sure.
    $entities = is_array($entities) ? array_filter($entities, function ($entity) {
      return $entity instanceof PlanEntity;
    }) : [];
    return $entities;
  }

  /**
   * Get the available attachment prototypes for the current plan context.
   *
   * @param \Drupal\ghi_subpages\Entity\LogframeSubpage $node
   *   The logframe node object.
   * @param \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[] $entities
   *   The plan entity objects.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype[]
   *   An array of attachment prototypes.
   */
  private function getAttachmentPrototypes(LogframeSubpage $node, array $entities) {
    $section = $node->getParentNode();
    if (!$section instanceof Section || !$section->getBaseObject() instanceof Plan) {
      return [];
    }

    $plan_id = $section->getBaseObject()->getSourceId();
    if (!$plan_id) {
      return [];
    }
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentPrototypeQuery $query */
    $query = $this->endpointQueryManager->createInstance('attachment_prototype_query');
    $attachment_prototypes = $query->getDataPrototypesForPlan($plan_id);
    return $this->filterAttachmentPrototypesByPlanEntities($attachment_prototypes, $entities);
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
