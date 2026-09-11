<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Interfaces\DeprecatedBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanGoverningEntitiesOverviewTable;
use Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentPrototypeQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\EntityQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\EntityPrototypeQuery;
use Drupal\ghi_subpages\SubpageManager;
use Drupal\node\NodeInterface;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;
use Drupal\Tests\ghi_subpages\Traits\SubpageTestTrait;
use Prophecy\Argument;

/**
 * Tests discovery, configuration and rendering of the combined overview table.
 *
 * @group ghi_blocks
 */
class PlanGoverningEntitiesOverviewTableTest extends PlanBlockKernelTestBase {

  use SubpageTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ghi_subpages'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createSubpageContentTypes();
    $this->createBaseObjectType(['id' => 'governing_entity']);
  }

  /**
   * Newly configured instances start with the name column only.
   */
  public function testEmptyDefaultsAndFinancialOnlyContext(): void {
    $plugin = $this->createOverviewTable();
    $this->assertSame([], $plugin->getBlockConfig()['table']['columns']);
    $form_state = (new FormState())->set('block', $plugin)->set('current_subform', 'table');
    $form = $plugin->tableForm([], $form_state);
    $this->assertSame(['entity_name'], array_column($form['columns']['#default_value'], 'item_type'));
    $this->assertSame([], $plugin->getConfigErrors());
    $this->assertArrayNotHasKey('spark_line_chart', $form['columns']['#allowed_item_types']);
    $this->assertArrayHasKey('funding_data', $form['columns']['#allowed_item_types']);
    $before = $plugin->getBlockConfig();
    $plugin->fixConfigErrors();
    $this->assertSame($before, $plugin->getBlockConfig());
  }

  /**
   * Data and display settings survive submission from the reorganized tabs.
   */
  public function testConfigurationTabSubmission(): void {
    $plugin = $this->createOverviewTable();
    $this->assertSame('data', $plugin->getDefaultSubform(TRUE));
    $this->assertSame('display', $plugin->getTitleSubform());
    $form_state = (new FormState())->set('block', $plugin)->set('current_subform', 'display');
    $display = $plugin->displayForm([], $form_state);
    $this->assertNull($display['soft_limit']['#default_value']);
    $this->assertArrayNotHasKey('hide_target_values_for_projects', $display);

    $data = ['include_unpublished_clusters' => TRUE];
    $columns = [['item_type' => 'entity_name', 'config' => ['label' => 'Sector']]];
    $form_state->setTemporaryValue('data', $data);
    $form_state->setTemporaryValue('table', ['columns' => $columns]);
    $form_state->setValues([
      'display' => [
        'label' => 'Custom overview',
        'label_display' => TRUE,
        'soft_limit' => '',
        'include_cluster_not_reported' => TRUE,
        'include_shared_funding' => TRUE,
      ],
    ]);
    $trigger = ['#parents' => ['actions', 'submit']];
    $form_state->setTriggeringElement($trigger);
    $plugin->blockSubmit(['actions' => ['submit' => $trigger]], $form_state);
    $configuration = $plugin->getConfiguration();
    $this->assertSame('Custom overview', $configuration['label']);
    $this->assertTrue($configuration['hpc']['data']['include_unpublished_clusters']);
    $this->assertTrue($configuration['hpc']['display']['include_cluster_not_reported']);
    $this->assertTrue($configuration['hpc']['display']['include_shared_funding']);
    $this->assertArrayNotHasKey('base', $configuration['hpc']);
    $this->assertSame($columns, $configuration['hpc']['table']['columns']);

    $reopened = $this->createOverviewTable($configuration['hpc']);
    $this->assertSame('table', $reopened->getDefaultSubform());
    $form_state = (new FormState())->set('block', $reopened)->set('current_subform', 'display');
    $display = $reopened->displayForm([], $form_state);
    $this->assertSame('', $display['soft_limit']['#default_value']);
    $this->assertTrue($display['include_cluster_not_reported']['#default_value']);
    $this->assertTrue($display['include_shared_funding']['#default_value']);
    $form_state->set('current_subform', 'data');
    $data_form = $reopened->dataForm([], $form_state);
    $this->assertSame(['prototype_id', 'include_unpublished_clusters', 'cluster_restrict'], array_keys($data_form));
    $this->assertSame('Attachment prototype', (string) $data_form['prototype_id']['#title']);
    $this->assertArrayNotHasKey('include_non_caseloads', $data_form);
    $this->assertArrayNotHasKey('include_non_caseloads', $plugin->getBlockConfig()['data']);
    $this->assertTrue($data_form['include_unpublished_clusters']['#default_value']);
  }

  /**
   * Legacy instances survive while only the new table remains in the picker.
   */
  public function testLegacyPickerFiltering(): void {
    $manager = $this->container->get('plugin.manager.block');
    $definitions = array_intersect_key($manager->getDefinitions(), array_flip([
      'plan_governing_entities_table',
      'plan_governing_entities_caseloads_table',
      'plan_governing_entities_overview_table',
    ]));
    $this->assertCount(3, $definitions);
    ghi_blocks_plugin_filter_block__layout_builder_alter($definitions, []);
    $this->assertSame(['plan_governing_entities_overview_table'], array_keys($definitions));
    foreach (['plan_governing_entities_table', 'plan_governing_entities_caseloads_table'] as $id) {
      $plugin = $manager->createInstance($id);
      $this->assertInstanceOf(DeprecatedBlockInterface::class, $plugin);
      $this->assertNull($plugin->getBlockConfigForReplacement());
    }
  }

  /**
   * A name-only table works without a caseload or financial data source.
   */
  public function testNameOnlyRenderingAndDownload(): void {
    $plugin = $this->createOverviewTable([
      'table' => [
        'columns' => [
          ['item_type' => 'entity_name', 'config' => ['label' => 'Sector']],
        ],
      ],
    ]);
    $this->assertSame([], $plugin->buildDownloadData()['rows']);
    $object = $this->createBaseObject(['type' => 'governing_entity', 'label' => 'Health']);
    $entity = new GoverningEntity((object) [
      'Id' => $object->getSourceId(),
      'Name' => 'Health',
      'Description' => NULL,
      'CustomReference' => 'HEA',
    ]);
    $query = $this->prophesize(EntityQuery::class);
    $query->getEntitiesForPlan(Argument::cetera())->willReturn([$entity]);
    $query->getCacheTags()->willReturn(['entities:test']);
    $plugin->setQueryHandler('entities', $query->reveal());
    $build = $plugin->buildContent();
    $this->assertCount(1, $build['#rows']);
    $this->assertFalse($build['#sortable']);
    $this->assertSame('Sector', $build['#header'][0]['data']);
    $this->assertSame('Health', $build['#rows'][0]['data'][0]['export_value']);
    $this->assertContains('entities:test', $build['#cache']['tags']);
    $download = $plugin->buildDownloadData();
    $this->assertEquals($build['#rows'], $download['rows']);
  }

  /**
   * A valid source without attachments supports population columns and rows.
   */
  public function testPopulationSourceWithoutCaseloads(): void {
    $plugin = $this->createOverviewTable([
      'data' => ['prototype_id' => 10],
      'table' => [
        'columns' => [
          ['item_type' => 'entity_name', 'config' => ['label' => 'Sector']],
          [
            'item_type' => 'data_point',
            'config' => [
              'label' => 'People targeted',
              'data_point' => [
                'data_points' => [['metric_type' => 'target']],
                'processing' => 'single',
                'formatting' => 'number',
              ],
            ],
          ],
        ],
      ],
    ]);
    $object = $this->createBaseObject(['type' => 'governing_entity', 'label' => 'Health']);
    $entity = $this->createMock(GoverningEntity::class);
    $entity->method('id')->willReturn((int) $object->getSourceId());
    $entity->method('getDisplayName')->willReturn('Health');
    $entity->method('getEntityTypeRefCode')->willReturn('CL');
    $query = $this->prophesize(EntityQuery::class);
    $query->getEntitiesForPlan(Argument::cetera())->willReturn([$entity]);
    $query->getCacheTags()->willReturn([]);
    $plugin->setQueryHandler('entities', $query->reveal());
    $attachment_query = $this->prophesize(AttachmentQuery::class);
    $attachment_query->getAttachmentsByObject(Argument::cetera())->willReturn([]);
    $attachment_query->getCacheTags()->willReturn(['caseloads:test']);
    $plugin->setQueryHandler('attachment', $attachment_query->reveal());
    $prototypes = [];
    $definitions = [
      10 => ['caseload', ['CL']],
      20 => ['indicator', ['CL']],
      30 => ['caseload', ['SO']],
    ];
    foreach ($definitions as $id => [$type, $ref_codes]) {
      $prototype = $this->createMock(AttachmentPrototype::class);
      $prototype->method('id')->willReturn($id);
      $prototype->method('getType')->willReturn($type);
      $prototype->method('getName')->willReturn('Population');
      $prototype->method('getEntityRefCodes')->willReturn($ref_codes);
      $prototype->method('getFieldTypes')->willReturn(['target']);
      $prototype->method('getFields')->willReturn(['target' => 'People targeted']);
      $prototype->method('getPlanningFields')->willReturn(['target' => 'People targeted']);
      $prototypes[$id] = $prototype;
    }
    $prototype_query = $this->prophesize(AttachmentPrototypeQuery::class);
    $prototype_query->getDataPrototypesForPlan($plugin->getCurrentPlanId())->willReturn($prototypes);
    $prototype_query->getCacheTags()->willReturn(['prototypes:test']);
    $plugin->setQueryHandler('attachment_prototype', $prototype_query->reveal());
    $this->assertSame([10 => $prototypes[10]], $plugin->getUniquePrototypes());
    $this->assertSame([], $plugin->getConfigErrors());
    $form_state = (new FormState())->set('block', $plugin)->set('current_subform', 'data');
    $form = $plugin->dataForm([], $form_state);
    $this->assertSame([10 => 'Population'], $form['prototype_id']['#options']);
    $build = $plugin->buildContent();
    $this->assertCount(1, $build['#rows']);
    $this->assertNull($build['#rows'][0]['data'][1]['export_value']);
    $this->assertSame('string', $build['#rows'][0]['data'][1]['excel_format']);
    $this->assertContains('prototypes:test', $build['#cache']['tags']);
    $this->assertContains('caseloads:test', $build['#cache']['tags']);
    $this->assertEquals($build['#rows'], $plugin->buildDownloadData()['rows']);
  }

  /**
   * The shared visibility setting also applies without population columns.
   */
  public function testClusterVisibility(): void {
    $object = $this->createBaseObject(['type' => 'governing_entity', 'label' => 'Health']);
    $entity = new GoverningEntity((object) [
      'Id' => $object->getSourceId(),
      'Name' => 'Health',
      'Description' => NULL,
      'CustomReference' => 'HEA',
    ]);
    $cases = [
      'published' => [TRUE, TRUE, FALSE, 1],
      'unpublished with editor access' => [FALSE, TRUE, FALSE, 0],
      'unpublished without access' => [FALSE, FALSE, FALSE, 0],
      'include unpublished' => [FALSE, FALSE, TRUE, 1],
      'published but inaccessible' => [TRUE, FALSE, FALSE, 0],
      'include inaccessible' => [TRUE, FALSE, TRUE, 1],
      'no subpage' => [NULL, FALSE, FALSE, 1],
    ];
    foreach ($cases as $case => [$published, $allowed, $include, $expected]) {
      $subpage = $this->createMock(NodeInterface::class);
      $subpage->method('isPublished')->willReturn($published ?? FALSE);
      $subpage->method('getCacheTags')->willReturn(['node:visibility']);
      $subpage->method('getCacheContexts')->willReturn([]);
      $subpage->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
      $subpage->method('access')->willReturnCallback(function ($operation, $account = NULL, $return_as_object = FALSE) use ($allowed) {
        return $return_as_object ? AccessResult::allowedIf($allowed)->addCacheContexts(['user.permissions']) : $allowed;
      });
      $subpage->method('toUrl')->willReturn(Url::fromUserInput('/node/1'));
      $manager = $this->createMock(SubpageManager::class);
      $manager->method('loadSubpagesForBaseObjects')->willReturn($published === NULL ? [] : [$object->id() => $subpage]);
      $this->container->set('ghi_subpages.manager', $manager);
      $plugin = $this->createOverviewTable([
        'data' => ['include_unpublished_clusters' => $include],
        'table' => ['columns' => [['item_type' => 'entity_name', 'config' => ['label' => 'Sector']]]],
      ]);
      $query = $this->prophesize(EntityQuery::class);
      $query->getEntitiesForPlan(Argument::cetera())->willReturn([$entity]);
      $query->getCacheTags()->willReturn([]);
      $plugin->setQueryHandler('entities', $query->reveal());
      $build = $plugin->buildContent();
      $this->assertCount($expected, $build['#rows'] ?? [], $case);
      $this->assertCount($expected, $plugin->buildDownloadData()['rows'], $case);
      if ($published !== NULL) {
        $this->assertContains('node:visibility', $build['#cache']['tags']);
        $this->assertContains('user.permissions', $build['#cache']['contexts']);
      }
      if ($expected && !$allowed) {
        $this->assertArrayNotHasKey('#type', $build['#rows'][0]['data'][0]['data']);
      }
    }
  }

  /**
   * Create the actual plugin with mocked remote query dependencies.
   */
  private function createOverviewTable(array $configuration = []): PlanGoverningEntitiesOverviewTable {
    $plugin = $this->createBlockPlugin('plan_governing_entities_overview_table', $configuration, $this->getPlanSectionContexts());
    $query = $this->prophesize(EntityQuery::class);
    $query->getEntitiesForPlan(Argument::cetera())->willReturn([]);
    $query->getCacheTags()->willReturn([]);
    $plugin->setQueryHandler('entities', $query->reveal());
    $prototype_query = $this->prophesize(EntityPrototypeQuery::class);
    $prototype_query->getPlanPrototype(Argument::any())->willReturn(NULL);
    $prototype_query->getCacheTags()->willReturn([]);
    $plugin->setQueryHandler('entity_prototype', $prototype_query->reveal());
    $attachment_query = $this->prophesize(AttachmentQuery::class);
    $attachment_query->getCacheTags()->willReturn([]);
    $attachment_query->getAttachmentsByObject(Argument::cetera())->shouldNotBeCalled();
    $plugin->setQueryHandler('attachment', $attachment_query->reveal());
    $attachment_prototype_query = $this->prophesize(AttachmentPrototypeQuery::class);
    $attachment_prototype_query->getDataPrototypesForPlan(Argument::any())->willReturn([]);
    $attachment_prototype_query->getCacheTags()->willReturn([]);
    $plugin->setQueryHandler('attachment_prototype', $attachment_prototype_query->reveal());
    return $plugin;
  }

}
