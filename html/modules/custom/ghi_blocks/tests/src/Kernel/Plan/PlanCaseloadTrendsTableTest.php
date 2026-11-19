<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormState;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanCaseloadTrendsTable;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\PlanMetricResolver;
use Drupal\ghi_plans\ValueObject\PlanMetricValues;
use Drupal\hpc_downloads\Interfaces\HPCDownloadExcelInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPNGInterface;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the plan caseload trends block plugin.
 */
#[Group('ghi_blocks')]
class PlanCaseloadTrendsTableTest extends PlanBlockKernelTestBase {

  const PLAN_ID = 10;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createField('base_object', 'plan', 'string', 'field_plan_data_source', 'Plan metric source');
    $this->createField('base_object', 'plan', 'boolean', 'field_restricted', 'Is restricted');
    $container = \Drupal::getContainer();
    $container->set('ghi_plans.plan_metric_resolver', $this->createMock(PlanMetricResolver::class));
    \Drupal::setContainer($container);
  }

  /**
   * Tests the block properties.
   */
  public function testBlockProperties() {
    $plugin = $this->getBlockPlugin();
    $this->assertInstanceOf(PlanCaseloadTrendsTable::class, $plugin);
    $this->assertInstanceOf(OverrideDefaultTitleBlockInterface::class, $plugin);
    $this->assertInstanceOf(HPCDownloadExcelInterface::class, $plugin);
    $this->assertInstanceOf(HPCDownloadPNGInterface::class, $plugin);

    $this->assertEquals(10, $plugin->getBlockConfig()['soft_limit']);
    $this->assertEquals('Evolution of the humanitarian response', $plugin->label());
  }

  /**
   * Tests the block without context nodes.
   */
  public function testBlockNoContext() {
    $plugin = $this->createBlockPlugin('plan_caseload_trends_table', []);
    $this->assertIsArray($this->callPrivateMethod($plugin, 'getRelatedPlans'));
    $this->assertEmpty($this->callPrivateMethod($plugin, 'getRelatedPlans'));
    $this->assertNull($this->callPrivateMethod($plugin, 'buildTable'));
    $this->assertNull($this->callPrivateMethod($plugin, 'buildTableData'));
    $this->assertNull($this->callPrivateMethod($plugin, 'buildSourceData'));
    $this->assertNull($plugin->buildContent());
  }

  /**
   * Tests the block forms.
   */
  public function testBlockForms() {
    $plugin = $this->getBlockPlugin();

    $form_state = new FormState();
    $form_state->set('block', $plugin);
    $form = $plugin->getConfigForm([], $form_state);
    $this->assertArrayHasKey('columns', $form);
    $this->assertArrayHasKey('prioritized_target', $form['columns']['#options']);
    $this->assertArrayHasKey('prioritized_requirements', $form['columns']['#options']);
    $this->assertSame(0, $form['columns']['#default_value']['prioritized_target']);
    $this->assertSame(0, $form['columns']['#default_value']['prioritized_requirements']);
    $this->assertEquals(3, $form['soft_limit']['#min']);
    $this->assertEquals(10, $form['soft_limit']['#max']);
  }

  /**
   * Tests the retrieval of related sections.
   */
  public function testGetRelatedPlans() {
    $plugin = $this->getBlockPlugin();
    $related_plans = $this->callPrivateMethod($plugin, 'getRelatedPlans');
    $this->assertNotEmpty($related_plans);
    $this->assertCount(1, $related_plans);
    $this->assertSame([self::PLAN_ID], array_keys($related_plans));
  }

  /**
   * Tests source-aware publication rules for related plans.
   */
  public function testRelatedPlanPublicationRules(): void {
    $plugin = $this->getBlockPlugin();
    /** @var \Drupal\ghi_plans\Entity\Plan $current_plan */
    $current_plan = $plugin->getContextValue('plan');
    $focus_country_id = $current_plan->getFocusCountry()->id();

    $this->createPlanBaseObject([
      'field_original_id' => 11,
      'field_year' => 2026,
      'field_focus_country' => ['target_id' => $focus_country_id],
      'field_released' => ['value' => 0],
      'field_plan_data_source' => Plan::DATA_SOURCE_PLAN,
    ]);
    $this->createPlanBaseObject([
      'field_original_id' => 12,
      'field_year' => 2027,
      'field_focus_country' => ['target_id' => $focus_country_id],
      'field_released' => ['value' => 0],
      'field_plan_data_source' => Plan::DATA_SOURCE_GHO,
    ]);
    $this->createPlanBaseObject([
      'field_original_id' => 13,
      'field_year' => 2028,
      'field_focus_country' => ['target_id' => $focus_country_id],
      'field_released' => ['value' => 0],
      'field_restricted' => ['value' => 1],
      'field_plan_data_source' => Plan::DATA_SOURCE_GHO,
    ]);
    $this->createPlanBaseObject([
      'field_original_id' => 14,
      'field_year' => 2029,
      'field_focus_country' => ['target_id' => $focus_country_id],
      'field_released' => ['value' => 0],
      'field_plan_data_source' => Plan::DATA_SOURCE_GHO,
    ]);

    $related_plans = $this->callPrivateMethod($plugin, 'getRelatedPlans');
    $this->assertSame([14, 12, self::PLAN_ID], array_keys($related_plans));

    $this->injectMetricResolver($plugin, [
      12 => ['target' => 50],
    ]);
    $source_data = $this->callPrivateMethod($plugin, 'buildSourceData');
    $this->assertSame([2027, 2025], array_column($source_data, 'year'));
  }

  /**
   * Tests that a component override controls the effective source.
   */
  public function testSourceOverrideControlsReleaseCheck(): void {
    $gho_plugin = $this->getBlockPlugin(data_source: Plan::DATA_SOURCE_GHO);
    /** @var \Drupal\ghi_plans\Entity\Plan $gho_current_plan */
    $gho_current_plan = $gho_plugin->getContextValue('plan');
    $gho_candidate = $this->createPlanBaseObject([
      'field_original_id' => 11,
      'field_focus_country' => ['target_id' => $gho_current_plan->getFocusCountry()->id()],
      'field_released' => ['value' => 0],
      'field_plan_data_source' => Plan::DATA_SOURCE_PLAN,
    ]);
    $this->assertArrayHasKey($gho_candidate->getSourceId(), $this->callPrivateMethod($gho_plugin, 'getRelatedPlans'));

    $plan_plugin = $this->getBlockPlugin(data_source: Plan::DATA_SOURCE_PLAN);
    /** @var \Drupal\ghi_plans\Entity\Plan $plan_current_plan */
    $plan_current_plan = $plan_plugin->getContextValue('plan');
    $gho_candidate = $this->createPlanBaseObject([
      'field_original_id' => 12,
      'field_focus_country' => ['target_id' => $plan_current_plan->getFocusCountry()->id()],
      'field_released' => ['value' => 0],
      'field_plan_data_source' => Plan::DATA_SOURCE_GHO,
    ]);
    $this->assertArrayNotHasKey($gho_candidate->getSourceId(), $this->callPrivateMethod($plan_plugin, 'getRelatedPlans'));
  }

  /**
   * Tests the table data.
   */
  public function testBuildTableData() {
    $plugin = $this->getBlockPlugin(TRUE);
    $this->injectMetricResolver($plugin);
    $table_data = $this->callPrivateMethod($plugin, 'buildTableData');
    $this->assertNotEmpty($table_data);
    $this->assertCount(9, $table_data['header']);
    $this->assertCount(1, $table_data['rows']);
    $this->assertCount(9, $table_data['rows'][0]);
    $this->assertInstanceOf(CacheableMetadata::class, $table_data['cacheability']);
    $this->assertContains('plan_id:' . self::PLAN_ID, $table_data['cacheability']->getCacheTags());

    $requirements_cell = $table_data['rows'][0]['requirements'];
    $this->assertEquals(3000, $requirements_cell['data-raw-value']);
    $this->assertEquals('currency', $requirements_cell['data-column-type']);
    $this->assertEquals('financial', $requirements_cell['data-progress-group']);

    $prioritized_target_cell = $table_data['rows'][0]['prioritized_target'];
    $this->assertEquals(90, $prioritized_target_cell['data-raw-value']);
    $this->assertEquals('amount', $prioritized_target_cell['data-column-type']);
    $this->assertEquals('people', $prioritized_target_cell['data-progress-group']);

    $prioritized_requirements_cell = $table_data['rows'][0]['prioritized_requirements'];
    $this->assertEquals(2500, $prioritized_requirements_cell['data-raw-value']);
    $this->assertEquals('currency', $prioritized_requirements_cell['data-column-type']);
    $this->assertEquals('financial', $prioritized_requirements_cell['data-progress-group']);

    $funding_cell = $table_data['rows'][0]['funding'];
    $this->assertEquals(1000, $funding_cell['data-raw-value']);
    $this->assertEquals('currency', $funding_cell['data-column-type']);
    $this->assertEquals('financial', $funding_cell['data-progress-group']);

    $coverage_cell = $table_data['rows'][0]['coverage'];
    $this->assertEquals('hpc_percent', $coverage_cell['data']['#theme']);
    $this->assertTrue($coverage_cell['data']['#table_cell']);
    $this->assertEqualsWithDelta(33.3, $coverage_cell['data']['#percent'], 0.1);
    $this->assertEqualsWithDelta(33.3, $coverage_cell['data-raw-value'], 0.1);
    $this->assertEquals('percentage', $coverage_cell['data-column-type']);
    $this->assertEquals('coverage', $coverage_cell['data-progress-group']);
  }

  /**
   * Tests prioritized columns remain opt-in for existing configurations.
   */
  public function testPrioritizedColumnsAreOptIn(): void {
    $plugin = $this->getBlockPlugin();
    $this->injectMetricResolver($plugin);
    $table_data = $this->callPrivateMethod($plugin, 'buildTableData');

    $this->assertArrayNotHasKey('prioritized_target', $table_data['header']);
    $this->assertArrayNotHasKey('prioritized_requirements', $table_data['header']);
    $this->assertArrayNotHasKey('prioritized_target', $table_data['rows'][0]);
    $this->assertArrayNotHasKey('prioritized_requirements', $table_data['rows'][0]);
  }

  /**
   * Tests an empty column selection shows all available columns.
   */
  public function testEmptyColumnSelection(): void {
    $plugin = $this->getBlockPlugin(FALSE, FALSE);
    $this->injectMetricResolver($plugin);
    $table_data = $this->callPrivateMethod($plugin, 'buildTableData');

    $this->assertCount(12, $table_data['header']);
    $this->assertArrayHasKey('target_percent', $table_data['header']);
    $this->assertArrayHasKey('reached', $table_data['header']);
    $this->assertArrayHasKey('prioritized_target', $table_data['header']);
    $this->assertArrayHasKey('prioritized_requirements', $table_data['header']);
  }

  /**
   * Tests the download data.
   */
  public function testBuildDownloadData() {
    $plugin = $this->getBlockPlugin(TRUE);
    $this->injectMetricResolver($plugin);
    $table_data = $this->callPrivateMethod($plugin, 'buildTableData');
    $this->assertEquals([
      'header' => $table_data['header'],
      'rows' => $table_data['rows'],
    ], $plugin->buildDownloadData());
  }

  /**
   * Tests the source data.
   */
  public function testBuildSourceData() {
    $plugin = $this->getBlockPlugin();
    $this->injectMetricResolver($plugin);
    $source_data = $this->callPrivateMethod($plugin, 'buildSourceData');
    $this->assertNotEmpty($source_data);
    $this->assertCount(1, $source_data);
    $this->assertEquals('2025', $source_data[0]['year']);
    $this->assertNotEmpty($source_data[0]['plan_type']);
    $this->assertNotEmpty($source_data[0]['plan_type_link']);
    $this->assertNotEmpty($source_data[0]['plan_type_tooltip']);
    $this->assertEquals(300, $source_data[0]['in_need']);
    $this->assertEquals(100, $source_data[0]['target']);
    $this->assertEquals(90, $source_data[0]['prioritized_target']);
    $this->assertEquals(round(100 / 3, 1), round($source_data[0]['target_percent'], 1));
    $this->assertEquals(80, $source_data[0]['reached']);
    $this->assertEquals(80.0, $source_data[0]['reached_percent']);
    $this->assertEquals(3000, $source_data[0]['requirements']);
    $this->assertEquals(2500, $source_data[0]['prioritized_requirements']);
    $this->assertEquals(1000, $source_data[0]['funding']);
    $this->assertEqualsWithDelta(33.3, $source_data[0]['coverage'], 0.1);
    $this->assertNull($source_data[0]['footnotes']);
  }

  /**
   * Tests the build functions for multiple rows including empty ones.
   */
  public function testBuildWithEmptyRows() {
    $plugin = $this->getBlockPlugin();

    /** @var \Drupal\ghi_plans\Entity\Plan $plan */
    $plan = $plugin->getContextValue('plan');
    // Create a 2024 and a 2022 plan section besides the existing 2025 one.
    // Table rendering should insert an empty 2023 row between those source
    // rows.
    $this->createSection([
      'label' => 'Section node 2024',
      'field_base_object' => $this->createPlanBaseObject([
        'field_year' => 2024,
        'field_focus_country' => ['target_id' => $plan->getFocusCountry()->id()],
      ]),
    ]);
    $this->createSection([
      'label' => 'Section node 2022',
      'field_base_object' => $this->createPlanBaseObject([
        'field_year' => 2022,
        'field_focus_country' => ['target_id' => $plan->getFocusCountry()->id()],
      ]),
    ]);

    $this->injectMetricResolver($plugin);

    $source_data = $this->callPrivateMethod($plugin, 'buildSourceData');
    $this->assertCount(3, $source_data);

    $this->assertEquals('2025', $source_data[0]['year']);
    $this->assertNotNull($source_data[0]['plan_type']);

    $this->assertEquals('2024', $source_data[1]['year']);
    $this->assertNotNull($source_data[1]['plan_type']);

    $this->assertEquals('2022', $source_data[2]['year']);
    $this->assertNotNull($source_data[2]['plan_type']);

    $table_data = $this->callPrivateMethod($plugin, 'buildTableData');
    $this->assertArrayHasKey('header', $table_data);
    $this->assertArrayHasKey('rows', $table_data);

    $rows = $table_data['rows'];
    $this->assertCount(4, $rows);
    $this->assertEquals('2025', $rows[0]['year']['data']);
    $this->assertNotNull($rows[0]['plan_type']['data']);

    $this->assertEquals('2024', $rows[1]['year']['data']);
    $this->assertNotNull($rows[1]['plan_type']['data']);

    $this->assertEquals('2023', $rows[2]['data']['year']['data']);
    $this->assertNotNull($rows[2]['data']['plan_type']['data']);
    $this->assertEquals('There was no plan in this year.', (string) $rows[2]['data']['plan_type']['data-raw-value']);

    $this->assertEquals('2022', $rows[3]['year']['data']);
    $this->assertNotNull($rows[3]['plan_type']['data']);
  }

  /**
   * Tests the block build.
   */
  public function testBlockBuild() {
    $plugin = $this->getBlockPlugin(TRUE);
    $this->injectMetricResolver($plugin);
    $build = $plugin->buildContent();
    $this->assertNotEmpty($build);
    $this->assertIsArray($build['#lazy_builder']);
    $this->assertIsArray($build['#lazy_builder_preview']);
    $this->assertEquals($build['#lazy_builder_preview']['#theme'], 'table');
    $this->assertEquals($build['#lazy_builder_preview']['#progress_groups'], TRUE);
    $this->assertEquals($build['#lazy_builder_preview']['#sortable'], TRUE);
    $this->assertEquals($build['#lazy_builder_preview']['#soft_limit'], 10);
    $this->assertCount(9, $build['#lazy_builder_preview']['#header']);
    $this->assertCount(1, $build['#lazy_builder_preview']['#rows']);
    $this->assertContains('plan_id:' . self::PLAN_ID, $build['#lazy_builder_preview']['#cache']['tags']);
    $this->assertContains('plan_id:' . self::PLAN_ID, $build['#cache']['tags']);
  }

  /**
   * Get a block plugin.
   *
   * @param bool $prioritized_columns
   *   Whether to enable the prioritized columns.
   * @param bool $select_columns
   *   Whether to select the configured columns.
   * @param string $data_source
   *   The configured plan metric source.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Plan\PlanCaseloadTrendsTable
   *   The block plugin.
   */
  private function getBlockPlugin(bool $prioritized_columns = FALSE, bool $select_columns = TRUE, string $data_source = 'default') {
    $configuration = [
      'columns' => [
        'in_need' => 'in_need',
        'target' => 'target',
        'target_percent' => 0,
        'reached' => 0,
        'reached_percent' => 0,
        'requirements' => 'requirements',
        'funding' => 'funding',
        'coverage' => 'coverage',
      ],
      'soft_limit' => 10,
      'data_source' => $data_source,
    ];
    if ($prioritized_columns) {
      $configuration['columns']['prioritized_target'] = 'prioritized_target';
      $configuration['columns']['prioritized_requirements'] = 'prioritized_requirements';
    }
    if (!$select_columns) {
      $configuration['columns'] = array_fill_keys(array_keys($configuration['columns']), 0);
    }
    $contexts = $this->getPlanSectionContexts([
      'field_original_id' => self::PLAN_ID,
      'field_year' => 2025,
    ]);

    $plugin = $this->createBlockPlugin('plan_caseload_trends_table', $configuration, $contexts);

    return $plugin;
  }

  /**
   * Injects source-aware metric values into the plugin.
   *
   * @param \Drupal\ghi_blocks\Plugin\Block\GHIBlockBase $plugin
   *   The plugin.
   * @param array $values_by_plan
   *   Metric values keyed by Fabric plan ID.
   */
  private function injectMetricResolver($plugin, array $values_by_plan = []) {
    $values_by_plan += [
      self::PLAN_ID => [
        'in_need' => 300,
        'target' => 100,
        'prioritized_target' => 90,
        'latest_reach' => 80,
        'requirements' => 3000,
        'prioritized_requirements' => 2500,
        'funding' => 1000,
      ],
    ];
    $resolver = $this->createMock(PlanMetricResolver::class);
    $resolver->method('resolve')->willReturnCallback(function (array $plans, ?string $source_override = NULL) use ($values_by_plan): array {
      $values = [];
      foreach ($plans as $plan_id => $plan) {
        $source = $source_override ?? $plan->getPlanDataSource();
        $values[$plan_id] = new PlanMetricValues($source, $values_by_plan[$plan_id] ?? [], cacheTags: ['plan_id:' . $plan_id]);
      }
      return $values;
    });
    $reflection = new \ReflectionClass($plugin);
    $reflection->getProperty('planMetricResolver')->setValue($plugin, $resolver);
  }

}
