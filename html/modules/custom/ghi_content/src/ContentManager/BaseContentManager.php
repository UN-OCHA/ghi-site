<?php

namespace Drupal\ghi_content\ContentManager;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Base manager service class..
 */
abstract class BaseContentManager {

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

}
