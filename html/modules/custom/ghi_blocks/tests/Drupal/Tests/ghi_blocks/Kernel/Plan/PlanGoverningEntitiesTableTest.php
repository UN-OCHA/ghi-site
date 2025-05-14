<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\Core\Form\FormState;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanGoverningEntitiesTable;
use Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery;
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

    $definition = $plugin->getPluginDefinition();
    $this->assertIsArray($definition['config_forms']);
    $this->assertCount(3, $definition['config_forms']);
    $this->assertArrayHasKey($plugin->getDefaultSubform(), $definition['config_forms']);
    $this->assertArrayHasKey($plugin->getTitleSubform(), $definition['config_forms']);
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
    $this->assertArrayHasKey(0, $table_data['rows']);
    $this->assertArrayHasKey(0, $table_data['rows'][0]);
    $this->assertEquals($cluster->label(), $table_data['rows'][0][0]['data-value']);
    $this->assertEquals($cluster->label(), $table_data['rows'][0][0]['data-raw-value']);
    $this->assertEquals($cluster->label(), $table_data['rows'][0][0]['export_value']);
    $this->assertEquals('Cluster name', $table_data['rows'][0][0]['data-content']);
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
    $this->assertNull($entity_objects);

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
    $entity_object = (object) ['id' => $cluster->getSourceId()];
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
    $configuration = $configuration !== FALSE ? [
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
    ] : [];
    $contexts = $this->getPlanSectionContexts();
    return $this->createBlockPlugin('plan_governing_entities_table', $configuration ?: [], $contexts);
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
    $plan_entities_query = $this->prophesize(PlanEntitiesQuery::class);
    $plan_entities_query->getPlanEntities(Argument::cetera())->willReturn(array_map(function ($cluster) {
      return (object) [
        'id' => $cluster->getSourceId(),
        'name' => $cluster->label(),
      ];
    }, $clusters));
    $plugin->setQueryHandler('entities', $plan_entities_query->reveal());
  }

}
