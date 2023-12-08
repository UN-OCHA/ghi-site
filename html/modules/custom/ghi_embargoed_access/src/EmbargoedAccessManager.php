<?php

namespace Drupal\ghi_embargoed_access;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Random;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Password\PasswordInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\protected_pages\ProtectedPagesStorage;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;

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
   * The system theme config object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Search API tracking manager.
   *
   * @var \Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager
   */
  protected $searchApiTrackingManager;

  /**
   * Constructs an embargoed access manager class.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ProtectedPagesStorage $protected_pages_storage, PasswordInterface $password, ConfigFactoryInterface $config_factory, ContentEntityTrackingManager $search_api_tracking_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->protectedPagesStorage = $protected_pages_storage;
    $this->password = $password;
    $this->configFactory = $config_factory;
    $this->searchApiTrackingManager = $search_api_tracking_manager;
  }

  /**
   * Check if the embargoed access is enabled or not.
   *
   * @return bool
   *   TRUE if the embargoed access is enabled, false otherwise.
   */
  public function embargoedAccessEnabled() {
    return $this->configFactory->get('ghi_embargoed_access.settings')->get('enabled');
  }

  /**
   * Load the id of a protected page item for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which to load the id.
   *
   * @return int|null
   *   The id of the protected page item or NULL if not found.
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
    $this->searchApiTrackingManager->entityUpdate($node);
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
    $this->searchApiTrackingManager->entityUpdate($node);
  }

  /**
   * Mark all embargoed nodes for re-index.
   */
  public function markAllForReindex() {
    $pages = $this->protectedPagesStorage->loadAllProtectedPages();
    if (empty($pages)) {
      return;
    }
    foreach ($pages as $page) {
      $params = Url::fromUri('internal:' . $page->path)?->getRouteParameters();
      if (!$params || empty($params['node'])) {
        continue;
      }
      $node = $this->entityTypeManager->getStorage('node')->load($params['node']);
      $this->searchApiTrackingManager->entityUpdate($node);
    }
  }

}
