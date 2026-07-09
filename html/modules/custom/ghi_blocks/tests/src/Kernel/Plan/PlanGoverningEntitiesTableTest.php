<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\Core\Form\FormState;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanGoverningEntitiesTable;
use Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity;
use Drupal\ghi_plans\Plugin\EndpointQuery\FlowSearchQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\EntityQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\GoverningEntityQuery;
use Drupal\hpc_api\Plugin\FabricQuery\IconQuery;
use Drupal\hpc_api\Query\EndpointQueryManager;
use Drupal\hpc_api\Query\FabricQueryManager;
use Drupal\hpc_downloads\Interfaces\HPCDownloadExcelInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPNGInterface;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;
use Drupal\Tests\ghi_subpages\Traits\SubpageTestTrait;
use Prophecy\Argument;

/**
 * Tests the plan governing entities table block plugin.
 *
 * @group ghi_blocks
 */
class PlanGoverningEntitiesTableTest extends PlanBlockKernelTestBase {

  use SubpageTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ghi_subpages',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createSubpageContentTypes();
    $this->createBaseObjectType([
      'id' => 'governing_entity',
    ]);
  }

  /**
   * Tests the block properties.
   */
  public function testBlockProperties() {
    $plugin = $this->getBlockPlugin(FALSE);
    $this->assertInstanceOf(PlanGoverningEntitiesTable::class, $plugin);
    $this->assertInstanceOf(MultiStepFormBlockInterface::class, $plugin);
    $this->assertInstanceOf(OverrideDefaultTitleBlockInterface::class, $plugin);
    $this->assertInstanceOf(HPCDownloadExcelInterface::class, $plugin);
    $this->assertInstanceOf(HPCDownloadPNGInterface::class, $plugin);

    $allowed_item_types = $plugin->getAllowedItemTypes();
    $this->assertCount(3, $allowed_item_types);
    $this->assertArrayHasKey('entity_name', $allowed_item_types);
    $this->assertArrayHasKey('funding_data', $allowed_item_types);
    $this->assertArrayHasKey('project_counter', $allowed_item_types);

    $this->assertEquals('Cluster overview', $plugin->label());

    $config_forms = $plugin->metadata()->configForms;
    $this->assertIsArray($config_forms);
    $this->assertCount(3, $config_forms);
    $this->assertArrayHasKey($plugin->getDefaultSubform(), $config_forms);
    $this->assertArrayHasKey($plugin->getTitleSubform(), $config_forms);
    $this->assertEquals('base', $plugin->getDefaultSubform());
    $this->assertEquals('base', $plugin->getTitleSubform());

    $plugin = $this->getBlockPlugin();
    $this->assertEquals('table', $plugin->getDefaultSubform());
  }

  /**
   * Tests the buildContent method.
   */
  public function testBuildContent() {
    $plugin = $this->getBlockPlugin();
    $build = $plugin->buildContent();
    $this->assertNull($build);

    $cluster = $this->createBaseObject(['type' => 'governing_entity']);
    $this->injectPlanEntityQueryStub($plugin, [$cluster]);
    $build = $plugin->buildContent();
    $this->assertIsArray($build);
    $this->assertEquals($build['#theme'], 'table');
    $this->assertEquals($build['#progress_groups'], TRUE);
    $this->assertEquals($build['#sortable'], TRUE);
    $this->assertEquals(0, $build['#soft_limit']);
    $this->assertCount(1, $build['#header']);
    $this->assertCount(1, $build['#rows']);
  }

  /**
   * Tests the buildTableData method.
   */
  public function testBuildTableData() {
    $plugin = $this->getBlockPlugin();
    $table_data = $this->callPrivateMethod($plugin, 'buildTableData');
    $this->assertNull($table_data);

    $cluster = $this->createBaseObject(['type' => 'governing_entity']);
    $this->injectPlanEntityQueryStub($plugin, [$cluster]);
    $table_data = $this->callPrivateMethod($plugin, 'buildTableData');
    $this->assertIsArray($table_data);
    $this->assertArrayHasKey('header', $table_data);
    $this->assertArrayHasKey('rows', $table_data);
    $this->assertEquals($cluster->label(), $table_data['rows'][0]['data'][0]['data-value'] ?? NULL);
    $this->assertEquals($cluster->label(), $table_data['rows'][0]['data'][0]['data-raw-value'] ?? NULL);
    $this->assertEquals($cluster->label(), $table_data['rows'][0]['data'][0]['export_value'] ?? NULL);
    $this->assertEquals('Cluster name', $table_data['rows'][0]['data'][0]['data-content'] ?? NULL);
    $this->assertEquals($cluster->getSourceId(), $table_data['rows'][0]['data-entity-id'] ?? NULL);
    $this->assertEquals('governing-entity', $table_data['rows'][0]['data-entity-type'] ?? NULL);
  }

  /**
   * Tests that special funding rows use their raw funding data.
   */
  public function testBuildTableDataSpecialFundingRows() {
    $flow_search_query = $this->mockFlowSearchQuery([
      'cluster_funding' => 52100000,
      'not_reported_funding' => 22600000,
      'shared_funding' => 218800000,
    ]);

    $endpoint_query_manager = $this->prophesize(EndpointQueryManager::class);
    $endpoint_query_manager->createInstance('flow_search_query')->willReturn($flow_search_query);
    $container = \Drupal::getContainer();
    $container->set('plugin.manager.endpoint_query_manager', $endpoint_query_manager->reveal());
    $container->set('plugin.manager.fabric_query_manager', $this->mockFabricQueryManager());
    \Drupal::setContainer($container);

    $plugin = $this->getBlockPlugin([
      'base' => [
        'include_cluster_not_reported' => TRUE,
        'include_shared_funding' => TRUE,
        'hide_target_values_for_projects' => FALSE,
        'hide_unpublished_clusters' => FALSE,
        'cluster_restrict' => [],
      ],
      'table' => [
        'columns' => [
          [
            'id' => 0,
            'item_type' => 'entity_name',
            'config' => [
              'label' => 'Cluster name',
            ],
          ],
          [
            'id' => 1,
            'item_type' => 'funding_data',
            'config' => [
              'label' => 'Funding',
              'data_type' => 'funding_totals',
            ],
          ],
        ],
      ],
    ]);
    $cluster = $this->createBaseObject([
      'type' => 'governing_entity',
      'name' => 'Water, Sanitation and Hygiene',
      'field_original_id' => 7912,
    ]);
    $this->injectPlanEntityQueryStub($plugin, [$cluster]);
    $plugin->setQueryHandler('flow_search', $flow_search_query);

    $table_data = $this->callPrivateMethod($plugin, 'buildTableData');

    $this->assertCount(3, $table_data['rows']);
    $this->assertEquals(52100000, $table_data['rows'][0]['data'][1]['data-value']);
    $this->assertEquals(22600000, $table_data['rows'][1][1]['data-value']);
    $this->assertEquals(218800000, $table_data['rows'][2][1]['data-value']);
  }

  /**
   * Tests the buildDownloadData method.
   */
  public function testBuildDownloadData() {
    $plugin = $this->getBlockPlugin();
    $table_data = $this->callPrivateMethod($plugin, 'buildTableData');
    $this->assertEquals($table_data, $plugin->buildDownloadData());
  }

  /**
   * Tests the block forms.
   */
  public function testBlockForms() {
    $plugin = $this->getBlockPlugin();

    $form_state = new FormState();
    $form_state->set('block', $plugin);

    // Test the base form.
    $base_form = $plugin->baseForm([], $form_state);
    $this->assertArrayHasKey('include_cluster_not_reported', $base_form);
    $this->assertArrayHasKey('include_shared_funding', $base_form);
    $this->assertArrayHasKey('hide_target_values_for_projects', $base_form);
    $this->assertArrayHasKey('cluster_restrict', $base_form);

    $table_form = $plugin->tableForm([], $form_state);
    $this->assertArrayHasKey('columns', $table_form);

    $display_form = $plugin->displayForm([], $form_state);
    $this->assertArrayHasKey('soft_limit', $display_form);
  }

  /**
   * Tests the getEntityObjects method.
   */
  public function testGetEntityObjects() {
    $plugin = $this->getBlockPlugin();
    $entity_objects = $this->callPrivateMethod($plugin, 'getEntityObjects');
    $this->assertIsArray($entity_objects);
    $this->assertEmpty($entity_objects);

    $this->injectPlanEntityQueryStub($plugin);
    $entity_objects = $this->callPrivateMethod($plugin, 'getEntityObjects');
    $this->assertIsArray($entity_objects);
  }

  /**
   * Tests the loadBaseObjectsForEntities method.
   */
  public function testLoadBaseObjectsForEntities() {
    $plugin = $this->getBlockPlugin();
    $base_objects = $this->callPrivateMethod($plugin, 'loadBaseObjectsForEntities', [[]]);
    $this->assertNull($base_objects);

    $cluster = $this->createBaseObject(['type' => 'governing_entity']);
    $entity_object = new GoverningEntity((object) [
      'Id' => $cluster->getSourceId(),
      'Name' => $cluster->label(),
      'Description' => NULL,
      'CustomReference' => $this->randomString(),
    ]);
    $base_objects = $this->callPrivateMethod($plugin, 'loadBaseObjectsForEntities', [[$entity_object]]);
    $this->assertIsArray($base_objects);
    $this->assertArrayHasKey($cluster->getSourceId(), $base_objects);
    $this->assertEquals($cluster->label(), $base_objects[$cluster->getSourceId()]->label());
  }

  /**
   * Tests the getFirstEntityObject method.
   */
  public function testGetFirstEntityObject() {
    $plugin = $this->getBlockPlugin();
    $entity_object = $this->callPrivateMethod($plugin, 'getFirstEntityObject');
    $this->assertNull($entity_object);

    $cluster = $this->createBaseObject(['type' => 'governing_entity']);
    $this->injectPlanEntityQueryStub($plugin, [$cluster]);
    $entity_object = $this->callPrivateMethod($plugin, 'getFirstEntityObject');
    $this->assertInstanceOf(BaseObjectInterface::class, $entity_object);
  }

  /**
   * Get a block plugin.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Plan\PlanGoverningEntitiesTable
   *   The block plugin.
   */
  private function getBlockPlugin($configuration = []) {
    if ($configuration === FALSE) {
      $configuration = [];
    }
    elseif (empty($configuration)) {
      $configuration = [
        'table' => [
          'columns' => [
            [
              'id' => 0,
              'item_type' => 'entity_name',
              'config' => [
                'label' => 'Cluster name',
              ],
            ],
          ],
        ],
      ];
    }
    $contexts = $this->getPlanSectionContexts();
    $plugin = $this->createBlockPlugin('plan_governing_entities_table', $configuration ?: [], $contexts);

    $attachment_query = $this->prophesize(AttachmentQuery::class);
    $attachment_query->getAttachmentsByObject('governingEntity', Argument::any(), ['cost'])->willReturn([]);

    $reflection = new \ReflectionClass($plugin);
    $property = $reflection->getProperty('queryHandlers');
    $property->setValue($plugin, [
      'attachment' => $attachment_query->reveal(),
    ]);

    return $plugin;
  }

  /**
   * Inject the plan entity query stub to the plugin.
   *
   * @param \Drupal\ghi_blocks\Plugin\Block\GHIBlockBase $plugin
   *   The plugin.
   * @param array $clusters
   *   An array of cluster base objects.
   */
  private function injectPlanEntityQueryStub($plugin, array $clusters = []) {
    $clusters = $clusters ?? [
      $this->createBaseObject(['type' => 'governing_entity']),
    ];
    $plan_entity_query = $this->prophesize(EntityQuery::class);
    $plan_entity_query->getEntitiesForPlan(Argument::cetera())->willReturn(array_map(function ($cluster) {
      return new GoverningEntity((object) [
        'Id' => $cluster->getSourceId(),
        'Name' => $cluster->label(),
        'Description' => NULL,
        'CustomReference' => $this->randomString(),
      ]);
    }, $clusters));
    $plugin->setQueryHandler('entities', $plan_entity_query->reveal());
  }

  /**
   * Mock the flow search query.
   *
   * @param int[] $funding
   *   Funding values keyed by funding type.
   *
   * @return \Drupal\ghi_plans\Plugin\EndpointQuery\FlowSearchQuery
   *   The mocked flow search query.
   */
  private function mockFlowSearchQuery(array $funding): FlowSearchQuery {
    $flow_search_query = $this->prophesize(FlowSearchQuery::class);
    $flow_search_query->setPlaceholder(Argument::cetera())->willReturn(NULL);
    $flow_search_query->getClusterTotalFunding(7912)->willReturn($funding['cluster_funding']);
    $flow_search_query->getNotSpecifiedCluster()->willReturn((object) [
      'id' => NULL,
      'name' => 'Not specified',
      'total_funding' => $funding['not_reported_funding'],
    ]);
    $flow_search_query->hasSharedClusterFunding()->willReturn(TRUE);
    $flow_search_query->getSharedClusterFunding()->willReturn($funding['shared_funding']);
    return $flow_search_query->reveal();
  }

  /**
   * Mock the Fabric query manager.
   *
   * @return \Drupal\hpc_api\Query\FabricQueryManager
   *   The mocked Fabric query manager.
   */
  private function mockFabricQueryManager(): FabricQueryManager {
    $icon_query = $this->prophesize(IconQuery::class);
    $governing_entity_query = $this->prophesize(GoverningEntityQuery::class);
    $attachment_query = $this->prophesize(AttachmentQuery::class);

    $fabric_query_manager = $this->prophesize(FabricQueryManager::class);
    $fabric_query_manager->hasDefinition(Argument::any())->willReturn(FALSE);
    $fabric_query_manager->createInstance('icon')->willReturn($icon_query->reveal());
    $fabric_query_manager->createInstance('governing_entity')->willReturn($governing_entity_query->reveal());
    $fabric_query_manager->createInstance('attachment')->willReturn($attachment_query->reveal());
    return $fabric_query_manager->reveal();
  }

}
