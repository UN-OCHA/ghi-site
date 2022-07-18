<?php

namespace Drupal\ghi_subpages;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_sections\SectionManager;

/**
 * Base manager service class..
 */
abstract class BaseObjectSubpageManager {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The section manager.
   *
   * @var \Drupal\ghi_sections\SectionManager
   */
  protected $sectionManager;

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
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a document manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, SectionManager $section_manager, RendererInterface $renderer, AccountInterface $current_user, MessengerInterface $messenger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->sectionManager = $section_manager;
    $this->renderer = $renderer;
    $this->currentUser = $current_user;
    $this->messenger = $messenger;
  }

}
