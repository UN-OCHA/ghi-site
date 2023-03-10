<?php

namespace Drupal\ghi_teams\Plugin\Action;

use Drupal\Core\Action\Plugin\Action\PublishAction as ActionPublishAction;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Publishes an entity.
 *
 * Wrapper around Drupal\Core\Action\Plugin\Action\PublishAction, taking the
 * publish content permissions into account.
 *
 * @Action(
 *   id = "entity:publish_action_access_check",
 *   action_label = @Translation("Publish"),
 *   deriver = "Drupal\Core\Action\Plugin\Action\Derivative\EntityPublishedActionDeriver",
 * )
 */
class PublishAction extends ActionPublishAction {

  /**
   * The publishcontent access service.
   *
   * @var \Drupal\publishcontent\Access\PublishContentAccess
   */
  protected $publishContentAccess;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->publishContentAccess = $container->get('publishcontent.access');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object instanceof NodeInterface) {
      $result = $this->publishContentAccess->access($account, $object);
      return $return_as_object ? $result : $result->isAllowed();
    }
    return parent::access($object, $account, $return_as_object);
  }

}
