<?php

namespace Drupal\ghi_content\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_content\Entity\ContentReviewInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom action to set the needs review flag on a node.
 *
 * @Action(
 *   id = "unset_needs_review",
 *   label = @Translation("Mark as 'reviewed'"),
 *   type = "node"
 * )
 */
class UnsetNeedsReview extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function execute($node = NULL) {
    if (!$node || !$node instanceof ContentReviewInterface) {
      return;
    }
    $node->needsReview(FALSE);
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
    Cache::invalidateTags($node->getCacheTags());
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!$object instanceof ContentReviewInterface) {
      return $return_as_object ? FALSE : AccessResult::forbidden();
    }
    /** @var \Drupal\Core\Access\AccessResultInterface $result */
    $result = $object->access('update', $account, TRUE);
    return $return_as_object ? $result : $result->isAllowed();
  }

}
