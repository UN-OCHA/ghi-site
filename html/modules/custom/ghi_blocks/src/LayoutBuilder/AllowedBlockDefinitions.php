<?php

namespace Drupal\ghi_blocks\LayoutBuilder;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\layout_builder\DefaultsSectionStorageInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder_restrictions\Plugin\LayoutBuilderRestrictionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Reuses Layout Builder Restrictions in custom import and copy workflows.
 */
class AllowedBlockDefinitions implements ContainerInjectionInterface {

  /**
   * Constructs the block definition lookup.
   *
   * @param \Drupal\Core\Block\BlockManagerInterface $blockManager
   *   The block plugin manager.
   * @param \Drupal\layout_builder_restrictions\Plugin\LayoutBuilderRestrictionManager $restrictionManager
   *   The Layout Builder Restrictions plugin manager.
   * @param \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $sectionStorageManager
   *   The section storage manager.
   */
  public function __construct(protected BlockManagerInterface $blockManager, protected LayoutBuilderRestrictionManager $restrictionManager, protected SectionStorageManagerInterface $sectionStorageManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('plugin.manager.block'), $container->get('plugin.manager.layout_builder_restriction'), $container->get('plugin.manager.layout_builder.section_storage'));
  }

  /**
   * Gets definitions allowed by the destination's configured restrictions.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The destination section storage.
   * @param \Drupal\Core\Entity\EntityInterface|null $destination
   *   The actual destination entity when importing into a default layout.
   *
   * @return array
   *   Block definitions keyed by plugin ID.
   */
  public function getDefinitions(SectionStorageInterface $section_storage, ?EntityInterface $destination = NULL): array {
    if ($section_storage instanceof DefaultsSectionStorageInterface && $destination !== NULL) {
      if (!$destination instanceof FieldableEntityInterface || !$destination->hasField(OverridesSectionStorage::FIELD_NAME)) {
        return [];
      }
      // Shared defaults do not identify the destination instance. Resolve its
      // context before filtering, without copying or changing either layout.
      $section_storage = $this->sectionStorageManager->load('overrides', [
        'entity' => EntityContext::fromEntity($destination),
        'view_mode' => new Context(new ContextDefinition('string'), $section_storage->getContextValue('display')->getMode()),
      ]);
      if (!$section_storage) {
        return [];
      }
    }
    // Check placement policy, not picker availability: imports may repair
    // context mappings after this restriction check.
    $definitions = $this->blockManager->getDefinitions();
    foreach (array_keys($this->restrictionManager->getSortedPlugins()) as $id) {
      $definitions = $this->restrictionManager->createInstance($id)->alterBlockDefinitions($definitions, ['section_storage' => $section_storage]);
    }
    return $definitions;
  }

}
