<?php

namespace Drupal\Tests\ghi_plan_clusters\Kernel;

use Drupal\ghi_plan_clusters\Entity\PlanCluster;
use Drupal\ghi_plan_clusters\Entity\PlanClusterInterface;
use Drupal\ghi_plan_clusters\PlanClusterManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;
use Drupal\Tests\ghi_subpages\Traits\SubpageTestTrait;

/**
 * Test class for plan cluster node bundles.
 *
 * @group ghi_plan_clusters
 */
class PlanClusterNodeBundleTest extends KernelTestBase {

  use SubpageTestTrait;
  use BaseObjectTestTrait;

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
    $this->createContentTypes();
  }

  /**
   * Test the usage of the bundle classes.
   */
  public function testBundleClass() {
    $plan_cluster = $this->entityTypeManager->getStorage('node')->create([
      'type' => PlanClusterInterface::BUNDLE,
      'title' => $this->randomMachineName(),
    ]);
    $plan_cluster->save();
    $this->assertInstanceOf('\\Drupal\ghi_plan_clusters\\Entity\\PlanCluster', $plan_cluster);
  }

  /**
   * Test the subpage logic for plan clusters.
   */
  public function testPlanClusterSubpages() {
    // Create a plan object.
    $plan = $this->createBaseObject([
      'type' => 'plan',
    ]);
    $plan->save();

    // Create a GVE object associated to that plan.
    $governing_entity = $this->createBaseObject([
      'type' => PlanClusterManager::BASE_OBJECT_BUNDLE_GOVERNING_ENTITY,
      'name' => 'Initial title',
      'field_plan' => $plan,
    ]);
    $governing_entity->save();

    // Confirm that there is no cluster subpage created yet for that GVE object.
    $plan_cluster = $this->planClusterManager->loadClusterSubpageForBaseObject($governing_entity);
    $this->assertNull($plan_cluster);

    // We must create a section first.
    $section = $this->createSection([
      'field_base_object' => $plan,
    ]);
    $section->save();

    // Confirm the cluster subpage exists now.
    $plan_cluster = $this->planClusterManager->loadClusterSubpageForBaseObject($governing_entity);
    $this->assertInstanceOf(PlanClusterInterface::class, $plan_cluster);
    $this->assertEquals($plan_cluster->label(), 'Initial title');

    // Confirm we can access the base objects.
    $governing_entities = $this->planClusterManager->loadGoverningEntityBaseObjectsForSection($section);
    $this->assertNotEmpty($governing_entities);
    $this->assertEquals($governing_entity->toArray(), $plan_cluster->getBaseObject()->toArray());

    // Confirm access to the base object type.
    $base_object_type = $plan_cluster->getBaseObjectType();
    $this->assertEquals(PlanClusterManager::BASE_OBJECT_BUNDLE_GOVERNING_ENTITY, $base_object_type->id());

    // Change the title of the GVE base object and confirm the change
    // propagates to the cluster subpage.
    $governing_entity->setName('New title');
    $governing_entity->save();
    $plan_cluster = $this->planClusterManager->loadClusterSubpageForBaseObject($governing_entity);
    $this->assertEquals($plan_cluster->label(), 'New title');

    // Delete the section and confirm that the cluster subpage is deleted too.
    $section->delete();
    $plan_cluster = $this->planClusterManager->loadClusterSubpageForBaseObject($governing_entity);
    $this->assertNull($plan_cluster);

    // Testing passing invalid values to some manager functions.
    $non_section_node = $this->prophesize(NodeInterface::class);
    $non_section_node->bundle()->willReturn('page');
    $null_result = $this->planClusterManager->loadNodesForSection($non_section_node->reveal());
    $this->assertNull($null_result);
    $null_result = $this->planClusterManager->loadSectionForClusterNode($non_section_node->reveal());
    $this->assertNull($null_result);
  }

  /**
   * Test the title override logic for plan clusters.
   */
  public function testTitleOverride() {
    $this->createField('node', PlanClusterInterface::BUNDLE, 'string', PlanCluster::TITLE_OVERRIDE_FIELD_NAME, 'Title override');

    // Create a plan object.
    $plan = $this->createBaseObject([
      'type' => 'plan',
    ]);
    $plan->save();

    // Create a GVE object associated to that plan.
    $governing_entity = $this->createBaseObject([
      'type' => PlanClusterManager::BASE_OBJECT_BUNDLE_GOVERNING_ENTITY,
      'name' => 'Initial title',
      'field_plan' => $plan,
    ]);
    $governing_entity->save();

    // Create a section.
    $section = $this->createSection([
      'field_base_object' => $plan,
    ]);
    $section->save();

    $plan_cluster = $this->planClusterManager->loadClusterSubpageForBaseObject($governing_entity);
    $this->assertEquals($plan_cluster->label(), 'Initial title');

    $plan_cluster->set(PlanCluster::TITLE_OVERRIDE_FIELD_NAME, 'Overridden title')->save();
    $plan_cluster = $this->planClusterManager->loadClusterSubpageForBaseObject($governing_entity);
    $this->assertEquals($plan_cluster->label(), 'Overridden title');
    $this->assertEquals($plan_cluster->getTitle(), 'Overridden title');
  }

  /**
   * Create a section type.
   *
   * @return \Drupal\node\Entity\NodeType
   *   The created node type.
   */
  private function createContentTypes() {
    $this->createBaseObjectType([
      'id' => PlanClusterManager::BASE_OBJECT_BUNDLE_GOVERNING_ENTITY,
      'label' => 'Governing entity',
    ]);

    $this->createEntityReferenceField('base_object', PlanClusterManager::BASE_OBJECT_BUNDLE_GOVERNING_ENTITY, 'field_plan', 'Plan', 'base_object', 'default', [
      'target_bundles' => ['plan'],
    ]);

    // Create the section bundle.
    $plan_cluster_type = $this->createContentType([
      'type' => PlanClusterInterface::BUNDLE,
      'name' => 'Plan cluster',
    ]);

    /** @var \Drupal\Core\Entity\EntityDisplayRepository $display_repository */
    $display_repository = $this->container->get('entity_display.repository');

    /** @var \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay $display */
    $display = $display_repository->getViewDisplay('node', PlanClusterInterface::BUNDLE);
    $display->enableLayoutBuilder()
      ->setOverridable()
      ->save();

    $this->createEntityReferenceField('node', PlanClusterInterface::BUNDLE, PlanClusterInterface::BASE_OBJECT_FIELD_NAME, 'Governing Entity', 'base_object', 'default', [
      'target_bundles' => [PlanClusterManager::BASE_OBJECT_BUNDLE_GOVERNING_ENTITY],
    ]);

    $display_repository->getFormDisplay('node', PlanClusterInterface::BUNDLE)
      ->setComponent('field_base_object', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'match_limit' => 10,
          'size' => 60,
          'placeholder' => '',
        ],
      ])
      ->save();

    $pattern = $this->createPattern('node', '[node:field_entity_reference:entity:url:path]/ge/[node:field_base_object:entity:field_original_id]');
    $this->addBundleCondition($pattern, 'node', PlanClusterInterface::BUNDLE);
    $pattern->save();

    return $plan_cluster_type;
  }

}
