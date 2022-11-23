<?php

namespace Drupal\ghi_element_sync;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for the element sync form.
 */
class SyncController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\ghi_element_sync\SyncManager
   */
  protected $syncManager;

  /**
   * Public constructor.
   */
  public function __construct(SyncManager $sync_manager) {
    $this->syncManager = $sync_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ghi_element_sync.sync_elements'),
    );
  }

  /**
   * Access callback for the element sync form.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function accessElementSyncForm(NodeInterface $node) {
    if (empty($this->syncManager->getSyncSourceUrl())) {
      return AccessResult::forbidden();
    }
    if (!in_array($node->bundle(), $this->syncManager->getAvailableNodeTypes())) {
      return AccessResult::forbidden();
    }
    if (!$node->access('update')) {
      return AccessResult::forbidden();
    }
    $base_object = BaseObjectHelper::getBaseObjectFromNode($node);
    return AccessResult::allowedIf($base_object && in_array($base_object->bundle(), SyncManager::BASE_OBJECT_TYPES_SUPPORTED));
  }

  /**
   * Title callback for the sync node form.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return string
   *   The page title.
   */
  public function syncElementsFormTitle(NodeInterface $node) {
    return $this->t('Sync elements for <em>@title: @type page</em>', [
      '@title' => $node->label(),
      '@type' => $node->type->entity->label(),
    ]);
  }

  /**
   * Title callback for the sync node form.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return string
   *   The page title.
   */
  public function syncMetadataFormTitle(NodeInterface $node) {
    return $this->t('Sync metadata for <em>@title: @type page</em>', [
      '@title' => $node->label(),
      '@type' => $node->type->entity->label(),
    ]);
  }

}
