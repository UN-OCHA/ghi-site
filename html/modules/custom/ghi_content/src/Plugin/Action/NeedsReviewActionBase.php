<?php

namespace Drupal\ghi_content\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_content\Entity\ContentReviewInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for custom actions to set or unset the needs review flag.
 */
abstract class NeedsReviewActionBase extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Do execute the flag action on the node.
   */
  public function doExecute($node, bool $flag_state) {
    if (!$node instanceof NodeInterface || !$node instanceof ContentReviewInterface) {
      return;
    }
    $node->needsReview($flag_state);
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
    Cache::invalidateTags($node->getCacheTags());
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!$object instanceof NodeInterface || !$object instanceof ContentReviewInterface) {
      return $return_as_object ? FALSE : AccessResult::forbidden();
    }
    /** @var \Drupal\Core\Access\AccessResultInterface $result */
    $result = $object->access('update', $account, TRUE);
    return $return_as_object ? $result : $result->isAllowed();
  }

}
