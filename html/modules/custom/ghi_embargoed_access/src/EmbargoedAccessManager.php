<?php

namespace Drupal\ghi_embargoed_access;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Random;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Password\PasswordInterface;
use Drupal\node\NodeInterface;
use Drupal\protected_pages\ProtectedPagesStorage;

/**
 * Embargoed access manager service class.
 */
class EmbargoedAccessManager {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The protected pages storage service.
   *
   * @var \Drupal\protected_pages\ProtectedPagesStorage
   */
  protected $protectedPagesStorage;

  /**
   * Provides the password hashing service object.
   *
   * @var \Drupal\Core\Password\PasswordInterface
   */
  protected $password;

  /**
   * Constructs an embargoed access manager class.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ProtectedPagesStorage $protected_pages_storage, PasswordInterface $password) {
    $this->entityTypeManager = $entity_type_manager;
    $this->protectedPagesStorage = $protected_pages_storage;
    $this->password = $password;
  }

  /**
   * Load the id of a protected page item for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which to load the id.
   *
   * @return int|null
   *   The if of the protected page item or NULL if not found.
   */
  public function loadProtectedPageIdForNode(NodeInterface $node) {
    if (!$node) {
      return;
    }
    $path = '/node/' . $node->id();
    $fields = ['pid'];
    $conditions = [
      'general' => [],
    ];
    $conditions['general'][] = [
      'field' => 'path',
      'value' => $path,
      'operator' => '=',
    ];
    return $this->protectedPagesStorage->loadProtectedPage($fields, $conditions, TRUE);
  }

  /**
   * Check if the given node is protected.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   *
   * @return bool
   *   TRUE if the node is currently protected, FALSE otherwise.
   */
  public function isProtected(NodeInterface $node) {
    $pid = $this->loadProtectedPageIdForNode($node);
    return !empty($pid);
  }

  /**
   * Protect the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to protect.
   */
  public function protectNode(NodeInterface $node) {
    if ($this->isProtected($node)) {
      // Already done.
      return;
    }
    $random = new Random();
    $page_data = [
      'password' => $this->password->hash(Html::escape($random->string(32))),
      'path' => '/node/' . $node->id(),
    ];
    $this->protectedPagesStorage->insertProtectedPage($page_data);
    Cache::invalidateTags($node->getCacheTags());
  }

  /**
   * Unprotect the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to unprotect.
   */
  public function unprotectNode(NodeInterface $node) {
    $pid = $this->loadProtectedPageIdForNode($node);
    if (!$pid) {
      // Already done.
      return;
    }
    $this->protectedPagesStorage->deleteProtectedPage($pid);
    Cache::invalidateTags($node->getCacheTags());
  }

}
