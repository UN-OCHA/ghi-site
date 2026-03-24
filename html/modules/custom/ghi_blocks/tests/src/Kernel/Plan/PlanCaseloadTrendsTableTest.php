<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\Core\Form\FormState;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanCaseloadTrendsTable;
use Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment;
use Drupal\ghi_plans\ApiObjects\Attachments\FinancialAttachment;
use Drupal\ghi_plans\Plugin\EndpointQuery\PlanFundingSummaryQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\PlanQuery;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_downloads\Interfaces\HPCDownloadExcelInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPNGInterface;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;
use Prophecy\Argument;

/**
 * Tests the plan caseload trends block plugin.
 *
 * @group ghi_blocks
 */
class PlanCaseloadTrendsTableTest extends PlanBlockKernelTestBase {

  const PLAN_ID = 10;

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
  }

  /**
   * Tests the table data.
   */
  public function testBuildTableData() {
    $plugin = $this->getBlockPlugin();
    $this->injectApiQueryStubs($plugin);
    $table_data = $this->callPrivateMethod($plugin, 'buildTableData');
    $this->assertNotEmpty($table_data);
    $this->assertCount(7, $table_data['header']);
    $this->assertCount(1, $table_data['rows']);
    $this->assertCount(7, $table_data['rows'][0]);

    // Requirements are allowed to be 0, because they come from the plan object
    // that is not entirely mocked.
    $requirements_cell = $table_data['rows'][0]['requirements'];
    $this->assertEquals(0, $requirements_cell['data-raw-value']);
    $this->assertEquals('currency', $requirements_cell['data-column-type']);
    $this->assertEquals('financial', $requirements_cell['data-progress-group']);

    $funding_cell = $table_data['rows'][0]['funding'];
    $this->assertEquals(1000, $funding_cell['data-raw-value']);
    $this->assertEquals('currency', $funding_cell['data-column-type']);
    $this->assertEquals('financial', $funding_cell['data-progress-group']);

    // Coverag is allowed to be 0, because they come from the plan object that
    // is not entirely mocked.
    $coverage_cell = $table_data['rows'][0]['coverage'];
    $this->assertEquals('hpc_percent', $coverage_cell['data']['#theme']);
    $this->assertEquals(0.0, $coverage_cell['data']['#percent']);
    $this->assertEquals(0.0, $coverage_cell['data-raw-value']);
    $this->assertEquals('percentage', $coverage_cell['data-column-type']);
    $this->assertEquals('coverage', $coverage_cell['data-progress-group']);
  }

  /**
   * Tests the download data.
   */
  public function testBuildDownloadData() {
    $plugin = $this->getBlockPlugin();
    $this->injectApiQueryStubs($plugin);
    $table_data = $this->callPrivateMethod($plugin, 'buildTableData');
    $this->assertEquals($table_data, $plugin->buildDownloadData());
  }

  /**
   * Tests the source data.
   */
  public function testBuildSourceData() {
    $plugin = $this->getBlockPlugin();
    $this->injectApiQueryStubs($plugin);
    $source_data = $this->callPrivateMethod($plugin, 'buildSourceData');
    $this->assertNotEmpty($source_data);
    $this->assertCount(1, $source_data);
    $this->assertEquals('2025', $source_data[0]['year']);
    $this->assertNotEmpty($source_data[0]['plan_type']);
    $this->assertNotEmpty($source_data[0]['plan_type_link']);
    $this->assertNotEmpty($source_data[0]['plan_type_tooltip']);
    $this->assertEquals(300, $source_data[0]['in_need']);
    $this->assertEquals(100, $source_data[0]['target']);
    $this->assertEquals(round(100 / 3, 1), round($source_data[0]['target_percent'], 1));
    $this->assertEquals(80, $source_data[0]['reached']);
    $this->assertEquals(80.0, $source_data[0]['reached_percent']);
    // Requirements are allowed to be 0, because they come from the plan object
    // that is not entirely mocked.
    $this->assertEquals(0, $source_data[0]['requirements']);
    $this->assertEquals(1000, $source_data[0]['funding']);
    // Coverag is allowed to be 0, because they come from the plan object that
    // is not entirely mocked.
    $this->assertEquals(0.0, $source_data[0]['coverage']);
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
    // This will create source data with one entry for 2023 and all other
    // values NULL.
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

    $this->injectApiQueryStubs($plugin);

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
    $plugin = $this->getBlockPlugin();
    $this->injectApiQueryStubs($plugin);
    $build = $plugin->buildContent();
    $this->assertNotEmpty($build);
    $this->assertIsArray($build['#lazy_builder']);
    $this->assertIsArray($build['#lazy_builder_preview']);
    $this->assertEquals($build['#lazy_builder_preview']['#theme'], 'table');
    $this->assertEquals($build['#lazy_builder_preview']['#progress_groups'], TRUE);
    $this->assertEquals($build['#lazy_builder_preview']['#sortable'], TRUE);
    $this->assertEquals($build['#lazy_builder_preview']['#soft_limit'], 10);
    $this->assertCount(7, $build['#lazy_builder_preview']['#header']);
    $this->assertCount(1, $build['#lazy_builder_preview']['#rows']);
  }

  /**
   * Get a block plugin.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Plan\PlanCaseloadTrendsTable
   *   The block plugin.
   */
  private function getBlockPlugin() {
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
    ];
    $contexts = $this->getPlanSectionContexts([
      'field_original_id' => self::PLAN_ID,
      'field_year' => 2025,
    ]);

    $plugin = $this->createBlockPlugin('plan_caseload_trends_table', $configuration, $contexts);

    $plan_funding_query = $this->prophesize(PlanFundingSummaryQuery::class);
    $attachment_query = $this->prophesize(AttachmentQuery::class);

    $plan_query = $this->prophesize(PlanQuery::class);
    $plan_query->getPlansById(Argument::any())->willReturn([]);

    $reflection = new \ReflectionClass($plugin);
    $property = $reflection->getProperty('queryHandlers');
    $property->setValue($plugin, [
      'plan_funding' => $plan_funding_query->reveal(),
      'attachment' => $attachment_query->reveal(),
      'plan' => $plan_query->reveal(),
    ]);

    return $plugin;
  }

  /**
   * Inject the plan entity query stub to the plugin.
   *
   * @param \Drupal\ghi_blocks\Plugin\Block\GHIBlockBase $plugin
   *   The plugin.
   */
  private function injectApiQueryStubs($plugin) {
    $endpoint_query = $this->prophesize(EndpointQuery::class);
    $plan_funding_query = $this->prophesize(PlanFundingSummaryQuery::class);
    $plan_funding_query->getData(Argument::cetera())->willReturn([
      'total_funding' => 1000,
    ]);
    $plan_funding_query->setPlaceholder('plan_id', Argument::type('integer'))->willReturn(NULL);
    $plan_funding_query->getFullEndpointUrl()->willReturn('https://api.hpc.tools/v2/fts/flow/plan/summary/' . rand(1, 10));
    $plan_funding_query_mock = $plan_funding_query->reveal();
    $plan_funding_query_mock->endpointQuery = $endpoint_query->reveal();
    $plugin->setQueryHandler('plan_funding', $plan_funding_query_mock);

    $caseload = $this->prophesize(CaseloadAttachment::class);
    $caseload->getCaseloadValue('in_need')->willReturn(300);
    $caseload->getCaseloadValue('target')->willReturn(100);
    $caseload->getCaseloadValue('latest_reach')->willReturn(80);

    $financial = $this->prophesize(FinancialAttachment::class);
    $financial->getRequirements()->willReturn(3000);
    $financial->getCoverage(1000)->willReturn(0.333);

    $attachment_query = $this->prophesize(AttachmentQuery::class);
    $attachment_query->getAttachmentsByPlan(Argument::any(), 'caseload')->willReturn([self::PLAN_ID => [$caseload->reveal()]]);
    $plugin->setQueryHandler('attachment', $attachment_query->reveal());
  }

}
