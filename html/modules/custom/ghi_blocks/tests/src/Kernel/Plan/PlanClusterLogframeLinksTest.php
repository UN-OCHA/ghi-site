<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\Core\Form\FormState;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanClusterLogframeLinks;
use Drupal\node\Entity\Node;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;
use Drupal\Tests\ghi_plan_clusters\Traits\PlanClusterTestTrait;
use Drupal\Tests\ghi_subpages\Traits\SubpageTestTrait;

/**
 * Tests the plan cluster logframe links block plugin.
 *
 * @group ghi_blocks
 */
class PlanClusterLogframeLinksTest extends PlanBlockKernelTestBase {

  use SubpageTestTrait;
  use PlanClusterTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ghi_subpages',
    'ghi_plan_clusters',
  ];

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

    $this->planClusterManager = $this->container->get('ghi_plan_clusters.manager');

    $this->createSubpageContentTypes();
    $this->createPlanClusterContentTypes();
  }

  /**
   * Tests the block properties.
   */
  public function testBlockProperties() {
    $plugin = $this->getBlockPlugin(FALSE);
    $this->assertInstanceOf(PlanClusterLogframeLinks::class, $plugin);
    $this->assertInstanceOf(OverrideDefaultTitleBlockInterface::class, $plugin);
    $this->assertEquals('Cluster Frameworks', $plugin->label());
  }

  /**
   * Tests the buildContent method.
   */
  public function testBuildContent() {
    $plugin = $this->getBlockPlugin();
    $build = $plugin->buildContent();
    $this->assertNull($build);

    $section_node = $plugin->getCurrentSectionNode();
    $this->assertNotNull($section_node);
    $this->assertTrue($section_node->isPublished());

    $plan_cluster = $this->createPlanCluster($section_node);
    $plan_cluster->setPublished()->save();
    $this->assertTrue($plan_cluster->isPublished());
    $this->planClusterManager->assureLogframeSubpagesForBaseNode($section_node);
    $build = $plugin->buildContent();
    $this->assertNotNull($build);
  }

  /**
   * Tests the getRenderableEntities method.
   */
  public function testGetRenderableEntities() {
    $plugin = $this->getBlockPlugin();
    $this->assertNull($this->callPrivateMethod($plugin, 'getRenderableEntities'));

    $this->createContentType(['type' => 'page']);
    $node = Node::create(['type' => 'page', 'title' => 'Page']);
    $node->save();
    // $plan_cluster = $this->createPlanCluster($section_node);
    $plugin->setContextValue('node', $node);
    $this->assertNull($this->callPrivateMethod($plugin, 'getRenderableEntities'));
  }

  /**
   * Tests the block forms.
   */
  public function testBlockForms() {
    $plugin = $this->getBlockPlugin();

    $form_state = new FormState();
    $form_state->set('block', $plugin);

    // Test the config form.
    $form = $plugin->getConfigForm([], $form_state);
    $this->assertEmpty($form);
  }

  /**
   * Get a block plugin.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Plan\PlanClusterLogframeLinks
   *   The block plugin.
   */
  private function getBlockPlugin() {
    $contexts = $this->getPlanSectionContexts();
    return $this->createBlockPlugin('plan_cluster_logframe_links', [], $contexts);
  }

}
