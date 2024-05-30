<?php

namespace Drupal\Tests\ghi_plan_clusters\Kernel;

use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\LogframeSubpage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\ghi_plan_clusters\Traits\PlanClusterTestTrait;

/**
 * Test class for section subpages tests.
 *
 * @group ghi_subpages
 */
class PlanClusterLogframeTest extends KernelTestBase {

  use PlanClusterTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'taxonomy',
    'field',
    'text',
    'filter',
    'token',
    'path',
    'path_alias',
    'pathauto',
    'layout_builder',
    'layout_discovery',
    'hpc_api',
    'hpc_common',
    'ghi_base_objects',
    'ghi_sections',
    'ghi_subpages',
    'ghi_plans',
    'ghi_plan_clusters',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The plan cluster manager.
   *
   * @var \Drupal\ghi_plan_clusters\PlanClusterManager
   */
  protected $planClusterManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('base_object');
    $this->installEntitySchema('path_alias');
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'field', 'pathauto']);

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->planClusterManager = $this->container->get('ghi_plan_clusters.manager');

    $this->createSubpageContentTypes();
    $this->createPlanClusterContentTypes();
  }

  /**
   * Test logic around plan cluster logframe subpages.
   */
  public function testPlanClusterLogframes() {
    // Create a plan cluster and confirm it has no logframe initially.
    $plan_cluster = $this->createPlanCluster();
    $this->assertNull($plan_cluster->getLogframeNode());

    // Get the section to test ::loadPlanClusterLogframeSubpageNodesForSection.
    $section = $plan_cluster->getParentBaseNode();
    $this->assertInstanceOf(SectionNodeInterface::class, $section);
    $this->assertEmpty($this->planClusterManager->loadPlanClusterLogframeSubpageNodesForSection($section));

    // Now trigger the creation of the logframe pages and confirm we have
    // exactly 1.
    $this->planClusterManager->assureLogframeSubpagesForBaseNode($section);
    $this->assertCount(1, $this->planClusterManager->loadPlanClusterLogframeSubpageNodesForSection($section));

    // Get it and confirm it's indeed a logframe.
    $logframe_node = $plan_cluster->getLogframeNode();
    $this->assertInstanceOf(LogframeSubpage::class, $logframe_node);

    // Confirm both are initially unpublished.
    $this->assertFalse($plan_cluster->isPublished());
    $this->assertFalse($logframe_node->isPublished());

    // Publish the cluster and confirm that this also publishes the logframe.
    $plan_cluster->setPublished();
    $plan_cluster->save();
    $logframe_node = $plan_cluster->getLogframeNode();
    $this->assertTRUE($plan_cluster->isPublished());
    $this->assertTRUE($logframe_node->isPublished());

    // Unpublish the cluster and confirm that this also unpublishes the
    // logframe.
    $plan_cluster->setUnpublished();
    $plan_cluster->save();
    $logframe_node = $plan_cluster->getLogframeNode();
    $this->assertFalse($plan_cluster->isPublished());
    $this->assertFalse($logframe_node->isPublished());

    // Create another cluster in the same section.
    $plan_cluster_2 = $this->createPlanCluster($section);
    $this->assertNull($plan_cluster_2->getLogframeNode());
    $this->assertCount(1, $this->planClusterManager->loadPlanClusterLogframeSubpageNodesForSection($section));
    $this->planClusterManager->assureLogframeSubpagesForBaseNode($section);
    $this->assertCount(2, $this->planClusterManager->loadPlanClusterLogframeSubpageNodesForSection($section));
  }

}
