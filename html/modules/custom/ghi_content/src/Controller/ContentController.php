<?php

namespace Drupal\ghi_content\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\ghi_content\RemoteSource\RemoteSourceManager;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for admin features on plans.
 */
class ContentController extends ControllerBase {

  /**
   * The remote source manager.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager
   */
  private $remoteSourceManager;

  /**
   * Public constructor.
   */
  public function __construct(RemoteSourceManager $remote_source_manager) {
    $this->remoteSourceManager = $remote_source_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.remote_source')
    );
  }

  /**
   * Access callback for the plan structure page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(NodeInterface $node) {
    $remote_sources = $this->remoteSourceManager->getDefinitions();
    return AccessResult::allowedIf($node->access('update') && !empty($remote_sources));
  }

}
