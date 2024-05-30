<?php

namespace Drupal\ghi_plan_clusters\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ghi_plan_clusters\PlanClusterManager;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for plan clusters.
 */
class PlanClusterController extends ControllerBase {

  /**
   * The logframe manager.
   *
   * @var \Drupal\ghi_plan_clusters\PlanClusterManager
   */
  private $planClusterManager;

  /**
   * Public constructor.
   */
  public function __construct(PlanClusterManager $plan_cluster_manager) {
    $this->planClusterManager = $plan_cluster_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ghi_plan_clusters.manager')
    );
  }

  /**
   * Route callback for creating longframe subpages for plan clusters.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The section node.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response to go back to the subpages form.
   */
  public function createLogframeSubpages(NodeInterface $node) {
    $this->planClusterManager->assureLogframeSubpagesForBaseNode($node);
    return $this->redirect('ghi_subpages.node.pages', ['node' => $node->id()]);
  }

}
