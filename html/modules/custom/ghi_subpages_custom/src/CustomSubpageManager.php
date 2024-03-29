<?php

namespace Drupal\ghi_subpages_custom;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\node\NodeInterface;

/**
 * Custom subpage manager service class.
 */
class CustomSubpageManager {

  use LayoutEntityHelperTrait;

  /**
   * The machine name of the bundle to use for custom subpages.
   */
  const BUNDLE = 'custom_subpage';

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a document manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Load all custom subpages for a section.
   *
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $section
   *   The section that custom subpage belong to.
   *
   * @return \Drupal\ghi_subpages_custom\Entity\CustomSubpage[]
   *   An array of custom subpage entity objects indexed by their ids.
   */
  public function loadNodesForSection(SectionNodeInterface $section) {
    $matching_nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => self::BUNDLE,
      'field_entity_reference' => $section->id(),
    ]);
    return $matching_nodes;
  }

  /**
   * Get the subheading page elements of the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return array
   *   An array of subheadings, keys are unique ids to be used in anchor links,
   *   values are the HTML escaped strings.
   */
  public function getSubHeadings(NodeInterface $node) {
    $section_storage = $this->getSectionStorageForEntity($node);
    $sections = $section_storage->getSections();
    if (!$sections || empty($sections) || !array_key_exists(0, $sections)) {
      return [];
    }
    $subheadings = [];
    foreach ($sections[0]->getComponents() as $component) {
      if ($component->getPluginId() != 'document_subheading' || !$component->getUuid()) {
        continue;
      }
      /** @var \Drupal\ghi_documents\Plugin\Block\DocumentSubHeading $block */
      $block = $component->getPlugin();
      $subheading = $block->getSubheading();
      if (!$subheading) {
        continue;
      }
      $subheadings[$block->getSubheadingId()] = $subheading;
    }
    return array_filter($subheadings);
  }

}
