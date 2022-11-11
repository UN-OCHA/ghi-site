<?php

namespace Drupal\ghi_documents;

use Drupal\ghi_content\ContentManager\BaseContentManager;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\node\NodeInterface;

/**
 * Document manager service class.
 */
class DocumentManager extends BaseContentManager {

  use LayoutEntityHelperTrait;

  /**
   * The machine name of the bundle to use for documents.
   */
  const DOCUMENT_BUNDLE = 'document';

  /**
   * Load all documents for a section.
   *
   * @param \Drupal\node\NodeInterface $section
   *   The section that documents belong to.
   *
   * @return \Drupal\node\NodeInterface[]|null
   *   An array of entity objects indexed by their ids.
   */
  public function loadNodesForSection(NodeInterface $section) {
    if ($section->bundle() != 'section') {
      return NULL;
    }

    $matching_documents = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => self::DOCUMENT_BUNDLE,
      'field_entity_reference' => $section->id(),
    ]);
    return !empty($matching_documents) ? $matching_documents : NULL;
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
