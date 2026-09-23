<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Global;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\ghi_blocks\Plugin\Block\GlobalPage\HistoricalTrends;
use Drupal\ghi_homepage\Entity\Homepage;
use Drupal\ghi_plans\Plugin\FabricQuery\PlanOverviewQuery;
use Drupal\hpc_api\Query\FabricQueryManager;
use Drupal\hpc_downloads\DownloadMethods\Excel;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Tests historical figures sourced from published homepage layouts.
 *
 * @group ghi_blocks
 */
class HistoricalTrendsTest extends PlanBlockKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ghi_homepage', 'hpc_downloads', 'phpexcel'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    NodeType::create(['type' => 'homepage', 'name' => 'Homepage'])->save();
    $this->createField('node', 'homepage', 'integer', 'field_year', 'Year');
    EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'homepage',
      'mode' => 'default',
      'status' => TRUE,
    ])->enableLayoutBuilder()->setOverridable()->save();

    $manager = $this->createMock(FabricQueryManager::class);
    $manager->method('hasDefinition')->willReturnCallback(fn ($id) => $id === 'plan_overview');
    $manager->method('createInstance')->with('plan_overview')->willReturnCallback(fn () => $this->mockPlanOverviewQuery());
    $this->container->set('plugin.manager.fabric_query_manager', $manager);
  }

  /**
   * Tests year-specific data, overrides, sums, zeroes and export parity.
   */
  public function testPublishedFigures(): void {
    $this->createHomepage(2024, [
      'people_in_need' => ['label' => 'An edited label', 'use_custom_value' => 1, 'custom_value' => '12345'],
      'people_target' => ['use_custom_value' => 1, 'custom_value' => '200', 'sum' => 1],
      'total_requirements' => ['use_custom_value' => 1, 'custom_value' => '0'],
      'funding_progress' => ['use_custom_value' => 1, 'custom_value' => '0.25', 'footnote' => 'Published adjustment'],
    ]);
    $this->createHomepage(2026);
    $this->createHomepage(2023, [], FALSE);
    $this->createHomepage(2027);

    $block = $this->createHistoricalTrendsBlock();
    $build = $block->buildContent();
    $this->assertCount(6, $build['#header']);
    $this->assertSame([2026, 2024], array_column(array_column($build['#rows'], 'year'), 'export_value'));
    $rows = $build['#rows'];
    $this->assertSame(3026, $rows[0]['people_in_need']['export_value']);
    $this->assertSame(12345, $rows[1]['people_in_need']['export_value']);
    $this->assertSame(4224, $rows[1]['people_target']['export_value']);
    $this->assertSame(0, $rows[1]['total_requirements']['export_value']);
    $this->assertSame(25.0, $rows[1]['funding_progress']['export_value']);
    $this->assertSame('Published adjustment', $rows[1]['funding_progress']['export_commentary']);
    $this->assertSame(25.0, $rows[1]['funding_progress']['data'][0]['#percent']);
    $this->assertSame('Published adjustment', $rows[1]['funding_progress']['data']['tooltips']['#tooltips']['#tooltip']['#plain_text']);
    $this->assertSame($rows, $block->buildDownloadData()['rows']);
    $this->assertEquals($build['#header'], $block->buildDownloadData()['header']);
  }

  /**
   * Tests ambiguous, ungrouped and free-text figures do not become data.
   */
  public function testMissingAndAmbiguousFigures(): void {
    $homepage = $this->createHomepage(2026);
    $section = $homepage->get('layout_builder__layout')->getSection(0);
    $components = $section->getComponents();
    $component = reset($components);
    $configuration = $component->get('configuration');
    $items = &$configuration['hpc']['key_figures']['items'];
    // Duplicate a typed figure even though its label is different.
    $items[] = [
      'id' => 20,
      'pid' => 0,
      'item_type' => 'plan_overview_data',
      'weight' => 20,
      'config' => ['type' => 'people_in_need', 'label' => 'Different scope'],
    ];
    // The ungrouped target is not rendered by the source block.
    $items[2]['pid'] = NULL;
    // An arbitrary label/value item cannot safely supply a typed column.
    $items[3] = [
      'id' => 3,
      'pid' => 0,
      'item_type' => 'label_value',
      'weight' => 3,
      'config' => ['label' => 'Requirements ($)', 'value' => '20 billion', 'formatting' => 'raw'],
    ];
    $component->setConfiguration($configuration);
    $homepage->save();

    $row = $this->createHistoricalTrendsBlock()->buildDownloadData()['rows'][0];
    foreach (['people_in_need', 'people_target', 'total_requirements'] as $key) {
      $this->assertNull($row[$key]['export_value']);
      $this->assertSame('', $row[$key]['data-raw-value']);
      $this->assertSame('No data', (string) $row[$key]['data']['#markup']);
      $this->assertArrayNotHasKey('data-progress-group', $row[$key]);
    }
    $this->assertNotNull($row['total_funding']['export_value']);
  }

  /**
   * Tests Excel keeps missing coverage blank and preserves numeric percentages.
   */
  public function testExcelFundingCoverage(): void {
    $this->createHomepage(2026)->set('layout_builder__layout', [])->save();
    $homepage = $this->createHomepage(2025);
    $components = $homepage->get('layout_builder__layout')->getSection(0)->getComponents();
    $component = reset($components);
    $configuration = $component->get('configuration');
    $duplicate = $configuration['hpc']['key_figures']['items'][5];
    $duplicate['id'] = 6;
    $configuration['hpc']['key_figures']['items'][] = $duplicate;
    $component->setConfiguration($configuration);
    $homepage->save();
    $this->createHomepage(2024, ['funding_progress' => ['use_custom_value' => 1, 'custom_value' => '0']]);
    $this->createHomepage(2023, ['funding_progress' => ['use_custom_value' => 1, 'custom_value' => '0.25']]);

    $data = $this->createHistoricalTrendsBlock()->buildDownloadData();
    $prepared = Excel::prepareExcelSheet($data['header'], $data['rows']);
    $headers = ['Meta data' => ['Source'], 'Export data' => $prepared['header']];
    $rows = ['Meta data' => [['Historical trends']], 'Export data' => $prepared['rows']];
    $options = [
      'creator' => 'HPC',
      'ignore_headers' => FALSE,
      'header' => $headers,
      'cell_formats' => [[], $prepared['cell_formats']],
      'footnotes' => [[], $prepared['footnotes']],
    ];
    $spreadsheet = new Spreadsheet();
    $exporter = $this->container->get('phpexcel');
    $exporter->setHeaders($spreadsheet, $headers, $options);
    $exporter->setColumns($spreadsheet, $rows, $headers, $options);
    $sheet = $spreadsheet->getSheetByName('Export data');

    // The exporter puts two logo rows and the column headers above the data.
    foreach (['F4', 'F5'] as $coordinate) {
      $this->assertSame('', $sheet->getCell($coordinate)->getFormattedValue());
      $this->assertSame('No data', $sheet->getComment($coordinate)->getText()->getPlainText());
    }
    $this->assertEquals(0, $sheet->getCell('F6')->getValue());
    $this->assertSame('0.00%', $sheet->getCell('F6')->getFormattedValue());
    $this->assertSame(0.25, $sheet->getCell('F7')->getValue());
    $this->assertSame('25.00%', $sheet->getCell('F7')->getFormattedValue());
    $spreadsheet->disconnectWorksheets();
  }

  /**
   * Tests hidden source blocks and repeated figures across separate blocks.
   */
  public function testMultipleAndHiddenSourceBlocks(): void {
    $homepage = $this->createHomepage(2026);
    $section = $homepage->get('layout_builder__layout')->getSection(0);
    $components = $section->getComponents();
    $configuration = reset($components)->get('configuration');
    $duplicate = new SectionComponent($this->container->get('uuid')->generate(), 'content', $configuration);
    $section->appendComponent($duplicate);
    $homepage->save();
    $block = $this->createHistoricalTrendsBlock();
    $this->assertNull($block->buildDownloadData()['rows'][0]['total_funding']['export_value']);

    $configuration['visibility_status'] = 'hidden';
    $homepage->get('layout_builder__layout')->getSection(0)->getComponent($duplicate->getUuid())->setConfiguration($configuration);
    $homepage->save();
    $this->assertNotNull($block->buildDownloadData()['rows'][0]['total_funding']['export_value']);
  }

  /**
   * Tests saved default revisions and invalidation on edits and publication.
   */
  public function testPublishedRevisionAndCacheDependencies(): void {
    $homepage = $this->createHomepage(2026, ['people_in_need' => ['use_custom_value' => 1, 'custom_value' => '100']]);
    $block = $this->createHistoricalTrendsBlock();
    $build = $block->buildContent();
    $this->assertContains('node:' . $homepage->id(), $build['#cache']['tags']);
    $this->assertContains('node_list:homepage', $build['#cache']['tags']);
    $cache = $this->container->get('cache.default');
    $cache->set('historical_trends_test', $build, -1, $build['#cache']['tags']);

    $homepage->setNewRevision(TRUE);
    $homepage->isDefaultRevision(FALSE);
    $sections = $homepage->get('layout_builder__layout')->getSections();
    $components = $sections[0]->getComponents();
    $component = reset($components);
    $configuration = $component->get('configuration');
    $configuration['hpc']['key_figures']['items'][1]['config']['custom_value'] = '999';
    $component->setConfiguration($configuration);
    $homepage->save();
    $this->assertSame(100, $block->buildDownloadData()['rows'][0]['people_in_need']['export_value']);

    $homepage->isDefaultRevision(TRUE);
    $homepage->save();
    $this->assertFalse($cache->get('historical_trends_test'));
    $this->assertSame(999, $block->buildDownloadData()['rows'][0]['people_in_need']['export_value']);

    $cache->set('historical_trends_test', $build, -1, $build['#cache']['tags']);
    $this->createHomepage(2025);
    $this->assertFalse($cache->get('historical_trends_test'));
    $homepage->setUnpublished()->save();
    $this->assertCount(1, $block->buildDownloadData()['rows']);
  }

  /**
   * Tests year and column settings, defaults, and invalid year ranges.
   */
  public function testConfiguration(): void {
    $this->createHomepage(2024);
    $this->createHomepage(2025);
    $this->createHomepage(2026);
    $block = $this->createHistoricalTrendsBlock([
      'start_year' => 2025,
      'end_year' => 2025,
      'columns' => [
        'people_in_need' => 'people_in_need',
        'people_target' => 0,
        'total_requirements' => 0,
        'total_funding' => 0,
        'funding_progress' => 0,
      ],
      'soft_limit' => 1,
    ]);
    $build = $block->buildContent();
    $this->assertSame(['year', 'people_in_need'], array_keys($build['#header']));
    $this->assertCount(1, $build['#rows']);
    $this->assertSame(2025, $build['#rows'][0]['year']['export_value']);
    $this->assertSame(1, $build['#soft_limit']);

    $block = $this->createHistoricalTrendsBlock();
    $form_state = new FormState();
    $form = $block->getConfigForm([], $form_state);
    $this->assertSame(['', 2026, 2025, 2024], array_keys($form['start_year']['#options']));
    $this->assertSame(5, count($form['columns']['#options']));
    $element = ['#parents' => ['settings', 'end_year']];
    $form_state->setValue(['settings', 'start_year'], 2026);
    $form_state->setValue(['settings', 'end_year'], 2024);
    $block->validateYearRange($element, $form_state);
    $this->assertTrue($form_state->hasAnyErrors());
  }

  /**
   * Tests an empty table still carries publication cache dependencies.
   */
  public function testNoPublishedHomepages(): void {
    $this->createHomepage(2026, [], FALSE);
    $build = $this->createHistoricalTrendsBlock()->buildContent();
    $this->assertSame([], $build['#rows']);
    $this->assertContains('node_list:homepage', $build['#cache']['tags']);
  }

  /**
   * Creates a trends block anchored to 2026.
   *
   * @param array $configuration
   *   Block settings to override.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\GlobalPage\HistoricalTrends
   *   The configured block.
   */
  private function createHistoricalTrendsBlock(array $configuration = []): HistoricalTrends {
    return $this->createBlockPlugin('global_historical_trends', $configuration, [
      'year' => new Context(new ContextDefinition('integer'), 2026),
    ]);
  }

  /**
   * Creates a homepage with grouped key figures.
   *
   * @param int $year
   *   The homepage year.
   * @param array $overrides
   *   Item configuration overrides keyed by data type.
   * @param bool $published
   *   Whether the homepage is published.
   *
   * @return \Drupal\ghi_homepage\Entity\Homepage
   *   The saved homepage.
   */
  private function createHomepage(int $year, array $overrides = [], bool $published = TRUE): Homepage {
    $items = [
      ['id' => 0, 'pid' => NULL, 'item_type' => 'item_group', 'weight' => 0, 'config' => ['label' => 'Figures']],
    ];
    foreach (['people_in_need', 'people_target', 'total_requirements', 'total_funding', 'funding_progress'] as $type) {
      $id = count($items);
      $items[] = [
        'id' => $id,
        'pid' => 0,
        'item_type' => 'plan_overview_data',
        'weight' => $id,
        'config' => ($overrides[$type] ?? []) + ['type' => $type],
      ];
    }
    $component = new SectionComponent($this->container->get('uuid')->generate(), 'content', [
      'id' => 'global_key_figures',
      'provider' => 'ghi_blocks',
      'hpc' => ['key_figures' => ['items' => $items]],
    ]);
    $homepage = Homepage::create([
      'type' => 'homepage',
      'title' => (string) $year,
      'field_year' => $year,
      'status' => $published,
      'layout_builder__layout' => [new Section('layout_onecol', [], [$component])],
    ]);
    $homepage->save();
    return $homepage;
  }

  /**
   * Mocks overview data with different values for each requested year.
   *
   * @return \Drupal\ghi_plans\Plugin\FabricQuery\PlanOverviewQuery
   *   The query mock retaining its real year setter and getter.
   */
  private function mockPlanOverviewQuery(): PlanOverviewQuery {
    $query = $this->getMockBuilder(PlanOverviewQuery::class)
      ->disableOriginalConstructor()
      ->onlyMethods([
        'getTotalRequirements',
        'getTotalFunding',
        'getCaseloadTotalValues',
        'getNumberOfGhoCountries',
        'getGhoPlans',
      ])
      ->getMock();
    $query->method('getTotalRequirements')->willReturn(10000.0);
    $query->method('getTotalFunding')->willReturn(2500.0);
    $query->method('getCaseloadTotalValues')->willReturnCallback(fn () => [
      'in_need' => 1000 + $query->getYear(),
      'target' => 2000 + $query->getYear(),
      'latest_reach' => 0,
      'expected_reach' => 0,
      'reached_custom' => 0,
      'target_custom' => 0,
    ]);
    $query->method('getNumberOfGhoCountries')->willReturn(0);
    $query->method('getGhoPlans')->willReturn([]);
    return $query;
  }

}
