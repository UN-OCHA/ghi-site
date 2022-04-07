<?php

namespace Drupal\ghi_content\ContentManager;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\migrate\Plugin\MigrationPluginManager;

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
   * The entity type manager service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The Drupal account to use for checking for access to block.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $migrationManager;

  /**
   * Constructs a document manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, AccountInterface $current_user, MigrationPluginManager $migration_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->currentUser = $current_user;
    $this->migrationManager = $migration_manager;
  }

}
