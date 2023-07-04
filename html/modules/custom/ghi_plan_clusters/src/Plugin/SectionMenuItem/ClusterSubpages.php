<?php

namespace Drupal\ghi_plan_clusters\Plugin\SectionMenuItem;

use Drupal\ghi_sections\Menu\SectionMenuItem;
use Drupal\ghi_sections\Menu\SectionMenuPluginBase;
use Drupal\ghi_sections\MenuItemType\SectionDropdown;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a cluster subpages item for section menus.
 *
 * @SectionMenuPlugin(
 *   id = "cluster_subpages",
 *   label = @Translation("Cluster subpages"),
 *   description = @Translation("This item links to the cluster subpages of a section."),
 *   weight = 1,
 * )
 */
class ClusterSubpages extends SectionMenuPluginBase {

  /**
   * The plan cluster manager.
   *
   * @var \Drupal\ghi_plan_clusters\PlanClusterManager
   */
  public $planClusterManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->planClusterManager = $container->get('ghi_plan_clusters.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->t('Clusters');
  }

  /**
   * {@inheritdoc}
   */
  public function getItem() {
    $item = new SectionMenuItem($this->getPluginId(), $this->getSection()->id(), $this->getLabel());
    return $item;
  }

  /**
   * {@inheritdoc}
   */
  public function getWidget() {
    $item = $this->getItem();
    return new SectionDropdown($item->getLabel(), $this->getClusterNodes());
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    $plan_cluster_nodes = $this->getClusterNodes();
    return !empty($plan_cluster_nodes);
  }

  /**
   * Get the cluster nodes.
   *
   * @return @return \Drupal\ghi_plan_clusters\Entity\PlanCluster[]|null
   *   An array of plan cluster nodes or NULL.
   */
  private function getClusterNodes() {
    $section = $this->getSection();
    if (!$section) {
      return [];
    }
    return $this->planClusterManager->loadNodesForSection($section) ?: [];
  }

}
