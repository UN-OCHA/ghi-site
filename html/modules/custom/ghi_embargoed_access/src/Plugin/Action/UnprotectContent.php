<?php

namespace Drupal\ghi_embargoed_access\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom action to remove a node from the protected pages.
 *
 * @Action(
 *   id = "unprotect_content",
 *   label = @Translation("Unprotect content"),
 *   type = "node"
 * )
 */
class UnprotectContent extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * The protected pages storage service.
   *
   * @var \Drupal\protected_pages\ProtectedPagesStorage
   */
  protected $protectedPagesStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->protectedPagesStorage = $container->get('protected_pages.storage');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($node = NULL) {
    if (!$node || !$node instanceof NodeInterface) {
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
    $pid = $this->protectedPagesStorage->loadProtectedPage($fields, $conditions, TRUE);
    if (!$pid) {
      // Already done.
      return;
    }
    $pid = $this->protectedPagesStorage->deleteProtectedPage($pid);
    Cache::invalidateTags($node->getCacheTags());
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\Core\Access\AccessResultInterface $result */
    $result = $object->access('update', $account, TRUE);
    $result->andIf(AccessResult::allowedIf($account->hasPermission('administer protected pages configuration')));
    return $return_as_object ? $result : $result->isAllowed();
  }

}
