<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\Core\Form\FormState;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanCaseloadTrendsTable;
use Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment;
use Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentSearchQuery;
use Drupal\ghi_plans\Plugin\EndpointQuery\FlowSearchQuery;
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

    $requirements_cell = $table_data['rows'][0]['requirements'];
    $this->assertEquals(3000, $requirements_cell['data-raw-value']);
    $this->assertEquals('currency', $requirements_cell['data-column-type']);
    $this->assertEquals('financial', $requirements_cell['data-progress-group']);

    $funding_cell = $table_data['rows'][0]['funding'];
    $this->assertEquals(1000, $funding_cell['data-raw-value']);
    $this->assertEquals('currency', $funding_cell['data-column-type']);
    $this->assertEquals('financial', $funding_cell['data-progress-group']);

    $coverage_cell = $table_data['rows'][0]['coverage'];
    $this->assertEquals('hpc_percent', $coverage_cell['data']['#theme']);
    $this->assertEquals(0.333, $coverage_cell['data']['#percent']);
    $this->assertEquals(0.333, $coverage_cell['data-raw-value']);
    $this->assertEquals('percentage', $coverage_cell['data-column-type']);
    $this->assertEquals('coverage', $coverage_cell['data-progress-group']);
  }

  /**
   * Tests the download data.
   */
  public function testBuildDownloadData() {
    $plugin = $this->getBlockPlugin();
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
    $this->assertEquals(3000, $source_data[0]['requirements']);
    $this->assertEquals(1000, $source_data[0]['funding']);
    $this->assertEquals(0.333, $source_data[0]['coverage']);
    $this->assertNull($source_data[0]['footnotes']);
  }

  /**
   * Tests the source data for multiple rows.
   */
  public function testBuildSourceDataMultipleRows() {
    $plugin = $this->getBlockPlugin();
    $plan_ids = [
      $plugin->getContextValue('plan')->getSourceId(),
    ];

    /** @var \Drupal\ghi_plans\Entity\Plan $plan */
    $plan = $plugin->getContextValue('plan');
    // Create a 2024 and a 2022 plan section besides the existing 2025 one.
    // This will create source data with one entry for 2023 and all other
    // values NULL.
    $section = $this->createSection([
      'label' => 'Section node 2024',
      'field_base_object' => $this->createPlanBaseObject([
        'field_year' => 2024,
        'field_focus_country' => ['target_id' => $plan->getFocusCountry()->id()],
      ]),
    ]);
    $plan_ids[] = $section->getBaseObject()->getSourceId();
    $section = $this->createSection([
      'label' => 'Section node 2022',
      'field_base_object' => $this->createPlanBaseObject([
        'field_year' => 2022,
        'field_focus_country' => ['target_id' => $plan->getFocusCountry()->id()],
      ]),
    ]);
    $plan_ids[] = $section->getBaseObject()->getSourceId();

    $this->injectApiQueryStubs($plugin, $plan_ids);

    $source_data = $this->callPrivateMethod($plugin, 'buildSourceData');
    $this->assertCount(4, $source_data);

    $this->assertEquals('2025', $source_data[0]['year']);
    $this->assertNotNull($source_data[0]['plan_type']);

    $this->assertEquals('2024', $source_data[1]['year']);
    $this->assertNotNull($source_data[1]['plan_type']);

    $this->assertEquals('2023', $source_data[2]['year']);
    $this->assertNull($source_data[2]['plan_type']);

    $this->assertEquals('2022', $source_data[3]['year']);
    $this->assertNotNull($source_data[3]['plan_type']);
  }

  /**
   * Tests the block build.
   */
  public function testBlockBuild() {
    $plugin = $this->getBlockPlugin();
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
    $contexts = $this->getPlanSectionContexts(['field_year' => 2025]);
    return $this->createBlockPlugin('plan_caseload_trends_table', $configuration, $contexts);
  }

  /**
   * Inject the plan entity query stub to the plugin.
   *
   * @param \Drupal\ghi_blocks\Plugin\Block\GHIBlockBase $plugin
   *   The plugin.
   * @param array $additional_plan_ids
   *   Optional plan ids for which to generate financial dummy data.
   */
  private function injectApiQueryStubs($plugin, $additional_plan_ids = []) {
    $plan = $plugin->getContextValue('plan');
    $financial_data = [
      $plan->getSourceId() => [
        'total_funding' => 1000,
        'current_requirements' => 3000,
        'funding_coverage' => 0.333,
      ],
    ];
    if (!empty($additional_plan_ids)) {
      foreach ($additional_plan_ids as $plan_id) {
        if (array_key_exists($plan_id, $financial_data)) {
          continue;
        }
        $financial_data[$plan_id] = [
          'total_funding' => 1000,
          'current_requirements' => 3000,
          'funding_coverage' => 0.333,
        ];
      }
    }
    $plan_funding_query = $this->prophesize(FlowSearchQuery::class);
    $plan_funding_query->getFinancialDataPerPlan(Argument::cetera())->willReturn($financial_data);
    $plugin->setQueryHandler('plan_funding', $plan_funding_query->reveal());

    $caseload = $this->prophesize(CaseloadAttachment::class);
    $caseload->getFieldByType('inNeed')->willReturn((object) ['value' => 300]);
    $caseload->getFieldByType('target')->willReturn((object) ['value' => 100]);
    $caseload->getCaseloadValue('latestReach')->willReturn(80);
    $attachment_search_query = $this->prophesize(AttachmentSearchQuery::class);
    $attachment_search_query->getAttachmentsByObject(Argument::cetera())->willReturn([$caseload->reveal()]);
    $plugin->setQueryHandler('attachment_search', $attachment_search_query->reveal());
  }

}
