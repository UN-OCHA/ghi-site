<?php

namespace Drupal\ghi_subpages;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_sections\SectionManager;
use Drupal\ghi_sections\SectionTrait;
use Drupal\ghi_subpages\Entity\SubpageManualInterface;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Subpage manager service class.
 */
class SubpageManager extends BaseSubpageManager {

  use SectionTrait;

  /**
   * The lock backend for concurrent subpage creation.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * Constructs the manager.
   */
  public function __construct(ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, SectionManager $section_manager, RendererInterface $renderer, AccountInterface $current_user, MessengerInterface $messenger, LockBackendInterface $lock) {
    parent::__construct($module_handler, $entity_type_manager, $entity_type_bundle_info, $section_manager, $renderer, $current_user, $messenger);
    $this->lock = $lock;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('ghi_sections.manager'),
      $container->get('renderer'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('lock'),
    );
  }

  /**
   * A list of node bundles that are supported as subpages.
   */
  const SUPPORTED_SUBPAGE_TYPES = [
    'population',
    'financials',
    'presence',
    'logframe',
    'progress',
    'needs',
    'response',
  ];

  /**
   * Get all available subpage types.
   *
   * @return array
   *   An array of node type machine names.
   */
  public function getStandardSubpageTypes() {
    // Load the basic subpages defined by this module, but make sure they
    // really exist.
    $node_types = $this->entityTypeManager->getStorage('node_type')->loadByProperties([
      'name' => self::SUPPORTED_SUBPAGE_TYPES,
    ]);
    return array_keys($node_types);
  }

  /**
   * Get all available subpage types.
   *
   * @return array
   *   An array of node type machine names.
   */
  public function getSubpageTypes() {
    // The basic subpages defined by this module.
    $subpage_types = [];
    $default_subpage_types = self::SUPPORTED_SUBPAGE_TYPES;

    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    foreach ($node_types as $node_type) {
      if (in_array($node_type->id(), $default_subpage_types)) {
        $subpage_types[] = $node_type->id();
        continue;
      }
      $is_subpage = FALSE;
      $this->moduleHandler->invokeAllWith('is_subpage_type', function (callable $hook, string $module) use ($node_type, &$is_subpage) {
        // If any module says yes, we accept that.
        $is_subpage = $is_subpage || $hook($node_type->id());
      });
      if ($is_subpage) {
        $subpage_types[] = $node_type->id();
      }
    }
    return $subpage_types;
  }

  /**
   * Load a subpage node for the given base object.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object for which to load a dedicated subpage.
   *
   * @return \Drupal\node\NodeInterface|null
   *   A node object or NULL.
   */
  public function loadSubpageForBaseObject(BaseObjectInterface $base_object) {
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => $this->getSubpageTypes(),
      'field_base_object' => $base_object->id(),
    ]);
    return count($nodes) == 1 ? reset($nodes) : NULL;
  }

  /**
   * Load all subpage nodes for the given base objects.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface[] $base_objects
   *   The base objects for which to load a dedicated subpage.
   *
   * @return \Drupal\node\NodeInterface[]
   *   An array of node objects.
   */
  public function loadSubpagesForBaseObjects(array $base_objects) {
    /** @var \Drupal\ghi_subpages\Entity\SubpageNodeInterface[] $nodes */
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => $this->getSubpageTypes(),
      'field_base_object' => array_map(fn ($base_object) => $base_object->id(), $base_objects),
    ]);
    $base_object_ids = array_map(fn ($node) => $node->get('field_base_object')->target_id, $nodes);
    return array_combine($base_object_ids, $nodes);
  }

  /**
   * Get all subpage nodes for a base node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The base node.
   *
   * @return \Drupal\node\NodeInterface[]|null
   *   An array of subpage nodes if found, NULL otherwhise.
   */
  public function loadSubpagesForBaseNode(NodeInterface $node) {
    return $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => SubpageManager::SUPPORTED_SUBPAGE_TYPES,
      'field_entity_reference' => $node->id(),
    ]);
  }

  /**
   * Assure that subpages for a base node exist.
   *
   * If they don't exist, this function will create the missing ones.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The base node.
   */
  public function assureSubpagesForBaseNode(NodeInterface $node) {
    // Keep full-catalog provisioning for callers that request it explicitly;
    // ordinary saves must not recreate pages removed by editors.
    $this->provisionStandardSubpages($node, $this->getStandardSubpageTypes());
  }

  /**
   * Provisions configured pages without manual access checks.
   *
   * Initial structure is system-provisioned, so it must not depend on whether
   * the section creator can later change its page structure manually.
   */
  public function provisionStandardSubpages(NodeInterface $section, array $bundles): array {
    return $this->createStandardSubpages($section, $bundles, FALSE);
  }

  /**
   * Loads the one standard subpage for a section and bundle.
   */
  public function loadStandardSubpage(NodeInterface $section, string $bundle): ?NodeInterface {
    if (!in_array($bundle, self::SUPPORTED_SUBPAGE_TYPES, TRUE)) {
      throw new \InvalidArgumentException("Unsupported standard subpage bundle: $bundle.");
    }
    $matches = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => $bundle,
      'field_entity_reference' => $section->id(),
    ]);
    if (count($matches) > 1) {
      throw new \UnexpectedValueException("Duplicate $bundle subpages for section {$section->id()}.");
    }
    return $matches ? reset($matches) : NULL;
  }

  /**
   * Creates one empty standard subpage, or returns the existing node.
   */
  public function createStandardSubpage(NodeInterface $section, string $bundle, bool $check_access = TRUE): NodeInterface {
    if (!$section instanceof SectionNodeInterface) {
      throw new \InvalidArgumentException('Standard subpages require a section parent.');
    }
    if (!in_array($bundle, self::SUPPORTED_SUBPAGE_TYPES, TRUE)) {
      throw new \InvalidArgumentException("Unsupported standard subpage bundle: $bundle.");
    }
    if ($check_access) {
      $this->assertManualStructureAccess($section, $bundle);
    }
    $lock = $this->lock;
    $lock_name = "ghi_subpages.create.{$section->id()}.$bundle";
    if (!$lock->acquire($lock_name)) {
      // A competing request may be provisioning this same page; wait rather
      // than fail immediately or proceed without exclusive creation access.
      $lock->wait($lock_name);
      if (!$lock->acquire($lock_name)) {
        throw new \RuntimeException("Could not obtain the creation lock for $bundle.");
      }
    }
    try {
      // The page may have appeared while we waited, so check under the lock
      // before saving another node for the same section and bundle.
      if ($existing = $this->loadStandardSubpage($section, $bundle)) {
        return $existing;
      }
      $node_type = $this->entityTypeManager->getStorage('node_type')->load($bundle);
      if (!$node_type) {
        throw new \UnexpectedValueException("Missing node type for $bundle.");
      }
      // Provision only the page structure: applying templates or default
      // content here would expand creation beyond the intended workflow.
      $subpage = $this->entityTypeManager->getStorage('node')->create([
        'type' => $bundle,
        'title' => $node_type->label(),
        'uid' => $section->getOwnerId(),
        'langcode' => $section->language()->getId(),
        'status' => NodeInterface::NOT_PUBLISHED,
        'field_entity_reference' => ['target_id' => $section->id()],
      ]);
      $subpage->save();
      return $subpage;
    }
    finally {
      $lock->release($lock_name);
    }
  }

  /**
   * Creates selected standard subpages.
   */
  public function createStandardSubpages(NodeInterface $section, array $bundles, bool $check_access = TRUE): array {
    $results = ['created' => [], 'existing' => [], 'failed' => []];
    foreach (array_unique($bundles) as $bundle) {
      try {
        $existing = $this->loadStandardSubpage($section, $bundle);
        $subpage = $this->createStandardSubpage($section, $bundle, $check_access);
        $results[$existing ? 'existing' : 'created'][$bundle] = $subpage;
      }
      catch (\Throwable $exception) {
        $results['failed'][$bundle] = $exception->getMessage();
      }
    }
    return $results;
  }

  /**
   * Deletes selected direct standard subpages after rechecking access.
   */
  public function deleteStandardSubpages(NodeInterface $section, array $nodes): void {
    if (!$this->currentUser->hasPermission('manage operation page structure') || !$section->access('update', $this->currentUser)) {
      throw new \LogicException('Not allowed to change the subpage structure.');
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $validated = [];
    // Reject an invalid selection before the first deletion so a stale or
    // foreign item cannot leave the section only partly changed.
    foreach ($nodes as $node) {
      $current = $storage->load($node->id());
      if (!$current || !in_array($current->bundle(), self::SUPPORTED_SUBPAGE_TYPES, TRUE) || (int) $current->get('field_entity_reference')->target_id !== (int) $section->id() || !$current->access('delete', $this->currentUser)) {
        throw new \LogicException('A selected subpage is missing or cannot be deleted.');
      }
      $validated[$current->id()] = $current;
    }
    foreach ($validated as $current) {
      $current->delete();
    }
  }

  /**
   * Checks both structural and ordinary entity creation access.
   *
   * Permission to change the section structure must not bypass normal node
   * creation access for the requested bundle.
   */
  protected function assertManualStructureAccess(NodeInterface $section, string $bundle): void {
    $node_access = $this->entityTypeManager->getAccessControlHandler('node');
    if (!$this->currentUser->hasPermission('manage operation page structure') || !$section->access('update', $this->currentUser) || !$node_access->createAccess($bundle, $this->currentUser)) {
      throw new \LogicException("Not allowed to create the $bundle subpage.");
    }
  }

  /**
   * Delete all subpages for a base node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The base node.
   */
  public function deleteSubpagesForBaseNode(NodeInterface $node) {
    if (!$this->isBaseTypeNode($node)) {
      return;
    }
    foreach (self::SUPPORTED_SUBPAGE_TYPES as $subpage_type) {
      $subpage_node = $this->getSubpageForBaseNode($node, $subpage_type);
      if (!$subpage_node) {
        continue;
      }
      $subpage_node->delete();
      $this->messenger->addStatus($this->t('Deleted @type subpage for @title', [
        '@type' => $subpage_node->getTitle(),
        '@title' => $node->getTitle(),
      ]));
    }
  }

  /**
   * Get the subpage node for a base node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The base node.
   * @param string $subpage_type
   *   A subpage type.
   *
   * @return \Drupal\node\NodeInterface|null
   *   A subpage node if found, NULL otherwhise.
   */
  public function getSubpageForBaseNode(NodeInterface $node, $subpage_type) {
    $matching_subpages = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => $subpage_type,
      'field_entity_reference' => $node->id(),
    ]);
    return !empty($matching_subpages) ? reset($matching_subpages) : NULL;
  }

  /**
   * Get all subpage nodes for a base node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The base node.
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node type for the custom subpage to fetch.
   *
   * @return \Drupal\ghi_subpages\Entity\SubpageNodeInterface[]|null
   *   An array of subpage nodes if found, NULL otherwhise.
   */
  public function getCustomSubpagesForBaseNode(NodeInterface $node, NodeTypeInterface $node_type) {
    $subpages = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => $node_type->id(),
      'field_entity_reference' => $node->id(),
    ]);
    $this->moduleHandler->alter('custom_subpages', $subpages, $node, $node_type);
    $subpages = array_filter($subpages, function ($subpage) {
      return $subpage instanceof SubpageNodeInterface;
    });
    return $subpages;
  }

  /**
   * Get the corresponding base type node for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The base type node if found.
   */
  public function getBaseTypeNode(NodeInterface $node) {
    if ($this->isBaseTypeNode($node)) {
      return $node;
    }
    if ($this->isSubpageTypeNode($node)) {
      if ($node->hasField('field_entity_reference')) {
        $base_type_node = $node->get('field_entity_reference')->entity;
      }
      $this->moduleHandler->alter('get_base_type_node', $base_type_node, $node);
      return $base_type_node;
    }
    return NULL;
  }

  /**
   * Get the label for the section overview page.
   *
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $node
   *   The base node.
   *
   * @return string|null
   *   The label of a section overview page.
   */
  public function getSectionOverviewLabel(SectionNodeInterface $node) {
    $base_object = $node->getBaseObject();
    if ($base_object) {
      return $this->t('@type overview', [
        '@type' => $base_object->type->entity->label(),
      ]);
    }
    return NULL;
  }

  /**
   * Check if the given node is a base type.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   *
   * @return bool
   *   TRUE if it is a base type, FALSE otherwhise.
   */
  public function isBaseTypeNode(NodeInterface $node) {
    return $node instanceof SectionNodeInterface;
  }

  /**
   * Check if the given node type is a subpage type.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node type to check.
   *
   * @return bool
   *   TRUE if it is a subpage type, FALSE otherwhise.
   */
  public function isSubpageType(NodeTypeInterface $node_type) {
    $subpage_types = $this->getSubpageTypes();
    return in_array($node_type->id(), $subpage_types);
  }

  /**
   * Check if the given node type is a manual subpage type.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node type to check.
   *
   * @return bool
   *   TRUE if it is a manual subpage type, FALSE otherwhise.
   */
  public function isManualSubpageType(NodeTypeInterface $node_type) {
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo('node');
    $class = $bundle_info[$node_type->id()]['class'] ?? NULL;
    return $class && is_subclass_of($class, SubpageManualInterface::class);
  }

  /**
   * Check if the given node is a subpage type.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   *
   * @return bool
   *   TRUE if it is a subpage type node, FALSE otherwhise.
   */
  public function isSubpageTypeNode(NodeInterface $node) {
    return $this->isSubpageType($node->type->entity);
  }

  /**
   * Check if the given node type is a standard subpage type.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node type to check.
   *
   * @return bool
   *   TRUE if it is a subpage type, FALSE otherwhise.
   */
  public function isStandardSubpageType(NodeTypeInterface $node_type) {
    $standard_subpage_types = self::SUPPORTED_SUBPAGE_TYPES;
    return in_array($node_type->id(), $standard_subpage_types);
  }

  /**
   * Check if the given node is a standard subpage type.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node type to check.
   *
   * @return bool
   *   TRUE if it is a subpage type, FALSE otherwhise.
   */
  public function isStandardSubpageTypeNode(NodeInterface $node) {
    return $this->isStandardSubpageType($node->type->entity);
  }

}
