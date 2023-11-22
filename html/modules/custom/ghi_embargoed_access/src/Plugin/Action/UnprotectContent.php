<?php

namespace Drupal\ghi_embargoed_access\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
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
   * The embargoed access manager service.
   *
   * @var \Drupal\ghi_embargoed_access\EmbargoedAccessManager
   */
  protected $embargoedAccessManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->embargoedAccessManager = $container->get('ghi_embargoed_access.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($node = NULL) {
    if (!$node || !$node instanceof NodeInterface) {
      return;
    }
    $this->embargoedAccessManager->unprotectNode($node);
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
