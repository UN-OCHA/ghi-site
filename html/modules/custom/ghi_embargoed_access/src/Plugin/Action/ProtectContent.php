<?php

namespace Drupal\ghi_embargoed_access\Plugin\Action;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Random;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom action to add a node to the protected pages.
 *
 * @Action(
 *   id = "protect_content",
 *   label = @Translation("Protect content"),
 *   type = "node"
 * )
 */
class ProtectContent extends ActionBase implements ContainerFactoryPluginInterface {

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->protectedPagesStorage = $container->get('protected_pages.storage');
    $instance->password = $container->get('password');
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
    if ($pid) {
      // Already done.
      return;
    }
    $random = new Random();
    $page_data = [
      'password' => $this->password->hash(Html::escape($random->string(32))),
      'path' => $path,
    ];
    $pid = $this->protectedPagesStorage->insertProtectedPage($page_data);
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
