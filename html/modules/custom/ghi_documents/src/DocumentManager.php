<?php

namespace Drupal\ghi_documents;

use Drupal\ghi_content\ContentManager\BaseContentManager;
use Drupal\node\NodeInterface;

/**
 * Document manager service class.
 */
class DocumentManager extends BaseContentManager {

  /**
   * Load all documents for a section.
   *
   * @param \Drupal\node\NodeInterface $section
   *   The section that documents belong to.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]|null
   *   An array of entity objects indexed by their ids.
   */
  public function loadNodesForSection(NodeInterface $section) {
    if ($section->bundle() != 'section') {
      return NULL;
    }

    $matching_documents = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'document',
      'field_entity_reference' => $section->id(),
    ]);
    return !empty($matching_documents) ? $matching_documents : NULL;
  }

}
