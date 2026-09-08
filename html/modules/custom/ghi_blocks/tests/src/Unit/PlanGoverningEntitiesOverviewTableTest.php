<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormState;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanGoverningEntitiesOverviewTable;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\DataPoint;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\MonitoringPeriod;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\SparkLineChart;
use Drupal\ghi_form_elements\ConfigurationContainerItemManager;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment;
use Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\Tests\UnitTestCase;

/**
 * Verifies GVE and caseload row composition without remote data dependencies.
 *
 * @group ghi_blocks
 * @coversDefaultClass \Drupal\ghi_blocks\Plugin\Block\Plan\PlanGoverningEntitiesOverviewTable
 */
class PlanGoverningEntitiesOverviewTableTest extends UnitTestCase {

  /**
   * Verify single and grouped caseloads never duplicate GVE funding.
   *
   * @dataProvider rowCases
   */
  public function testRowComposition(array $values, array $expected): void {
    $columns = $this->createColumns();
    $plugin = $this->mockOverviewTable($columns);
    $attachments = array_map(fn ($value) => $this->mockCaseloadWithValue($value), $values);
    $cacheability = new CacheableMetadata();
    $rows = $this->buildRows($plugin, $columns, $attachments, $cacheability);
    $actual = array_map(fn ($row) => array_column($row['data'], 'export_value'), $rows);
    $this->assertSame($expected, $actual);
    $this->assertContains('funding:test', $cacheability->getCacheTags());
    $this->assertSame(0, $cacheability->getCacheMaxAge());
    if (count($values) > 1) {
      $this->assertSame('string', $rows[1]['data'][2]['excel_format']);
    }
  }

  /**
   * Cases exercising missing, single and multiple caseloads.
   */
  public static function rowCases(): array {
    return [
      'single' => [[10], [['Protection', 10, 100]]],
      'multiple' => [[10, 20], [['Protection', NULL, 100], ['Caseload 10', 10, NULL], ['Caseload 20', 20, NULL]]],
      'missing included' => [[], [['Protection', NULL, 100]]],
      'zero is data' => [[0], [['Protection', 0, 100]]],
    ];
  }

  /**
   * Population filters retain parent grouping without evaluating empty cells.
   */
  public function testPopulationFilterPreservesGroup(): void {
    $columns = $this->createColumns();
    $columns[1]['config']['filter_min'] = 15;
    $plugin = $this->mockOverviewTable($columns);
    $cacheability = new CacheableMetadata();
    $rows = $this->buildRows($plugin, $columns, [$this->mockCaseloadWithValue(10), $this->mockCaseloadWithValue(20)], $cacheability);
    $this->assertSame([
      ['Protection', NULL, 100],
      ['Caseload 20', 20, NULL],
    ], array_map(fn ($row) => array_column($row['data'], 'export_value'), $rows));
    $this->assertContains('caseload:10', $cacheability->getCacheTags());
  }

  /**
   * Rejected caseloads do not reappear as empty cluster rows.
   */
  public function testPopulationFilterRemovesGroupButRetainsMissingCaseload(): void {
    $columns = $this->createColumns();
    $columns[1]['config']['filter_min'] = 30;
    $plugin = $this->mockOverviewTable($columns);
    $cacheability = new CacheableMetadata();
    $attachments = [$this->mockCaseloadWithValue(10), $this->mockCaseloadWithValue(20)];
    $this->assertSame([], $this->buildRows($plugin, $columns, $attachments, $cacheability));
    $this->assertContains('caseload:20', $cacheability->getCacheTags());
    $rows = $this->buildRows($plugin, $columns, [], $cacheability);
    $this->assertSame(['Protection', NULL, 100], array_column($rows[0]['data'], 'export_value'));
  }

  /**
   * Unnamed caseloads retain their values and receive distinct fallback labels.
   */
  public function testUnnamedCaseloads(): void {
    $columns = $this->createColumns();
    $plugin = $this->mockOverviewTable($columns);
    $plugin->setStringTranslation($this->getStringTranslationStub());
    $attachments = [];
    foreach ([10 => NULL, 20 => '  '] as $id => $description) {
      $attachment = $this->createMock(CaseloadAttachment::class);
      $attachment->method('id')->willReturn($id);
      $attachment->method('getDescription')->willReturn($description);
      $attachments[] = $attachment;
    }
    $cacheability = new CacheableMetadata();
    $rows = $this->buildRows($plugin, $columns, $attachments, $cacheability);
    $this->assertSame([
      ['Protection', NULL, 100],
      ['Caseload 10', 10, NULL],
      ['Caseload 20', 20, NULL],
    ], array_map(fn ($row) => array_column($row['data'], 'export_value'), $rows));
  }

  /**
   * A financial filter removes the complete group, including every child.
   */
  public function testFinancialFilterRemovesGroup(): void {
    $columns = $this->createColumns();
    $columns[2]['config']['filter_min'] = 200;
    $plugin = $this->mockOverviewTable($columns);
    $cacheability = new CacheableMetadata();
    $this->assertSame([], $this->buildRows($plugin, $columns, [$this->mockCaseloadWithValue(20)], $cacheability));
    $this->assertContains('funding:test', $cacheability->getCacheTags());
  }

  /**
   * Financial-only tables have no caseload requirement.
   */
  public function testFinancialOnly(): void {
    $columns = array_values(array_filter($this->createColumns(), fn ($column) => $column['item_type'] != 'data_point'));
    $plugin = $this->mockOverviewTable($columns);
    $cacheability = new CacheableMetadata();
    $rows = $this->buildRows($plugin, $columns, [], $cacheability);
    $this->assertSame(['Protection', 100], array_column($rows[0]['data'], 'export_value'));
  }

  /**
   * A source is automatic only when there is exactly one valid choice.
   */
  public function testPrototypeSelection(): void {
    $prototype = $this->createMock(AttachmentPrototype::class);
    $other = $this->createMock(AttachmentPrototype::class);
    $cases = [
      [[], NULL, NULL],
      [[10 => $prototype], NULL, $prototype],
      [[10 => $prototype, 20 => $other], NULL, NULL],
      [[10 => $prototype, 20 => $other], 20, $other],
      [[10 => $prototype], 99, NULL],
    ];
    foreach ($cases as [$prototypes, $selected, $expected]) {
      $methods = ['getBlockConfig', 'getUniquePrototypes'];
      $plugin = $this->getMockBuilder(PlanGoverningEntitiesOverviewTable::class)->disableOriginalConstructor()->onlyMethods($methods)->getMock();
      $plugin->method('getBlockConfig')->willReturn(['data' => ['prototype_id' => $selected]]);
      $plugin->method('getUniquePrototypes')->willReturn($prototypes);
      $this->assertSame($expected, $plugin->getAttachmentPrototype());
    }
  }

  /**
   * Invalid population sources and metrics are rejected without remapping.
   */
  public function testConfigurationValidation(): void {
    $prototype = $this->createMock(AttachmentPrototype::class);
    $prototype->method('getFieldTypes')->willReturn(['in_need', 'target']);
    $plan = $this->createMock(Plan::class);
    $cases = [['in_need', $prototype, 0], ['unavailable', $prototype, 1], ['in_need', NULL, 1]];
    foreach ($cases as [$metric, $source, $errors]) {
      $columns = [
        [
          'item_type' => 'data_point',
          'config' => ['data_point' => ['data_points' => [['metric_type' => $metric]]]],
        ],
      ];
      $methods = [
        'getBlockConfig',
        'getCurrentPlanObject',
        'getAttachmentPrototype',
        'getConfigurationContainerItemManager',
      ];
      $plugin = $this->getMockBuilder(PlanGoverningEntitiesOverviewTable::class)->disableOriginalConstructor()->onlyMethods($methods)->getMock();
      $plugin->setStringTranslation($this->getStringTranslationStub());
      $plugin->method('getBlockConfig')->willReturn(['table' => ['columns' => $columns]]);
      $plugin->method('getCurrentPlanObject')->willReturn($plan);
      $plugin->method('getConfigurationContainerItemManager')->willReturn($this->mockItemManagerWithDefinitions());
      $plugin->method('getAttachmentPrototype')->willReturn($source);
      $this->assertCount($errors, $plugin->getConfigErrors());
    }
  }

  /**
   * Editors can navigate to repair columns, but cannot save an invalid source.
   */
  public function testValidationAllowsStepNavigation(): void {
    $plugin = $this->getMockBuilder(PlanGoverningEntitiesOverviewTable::class)->disableOriginalConstructor()->onlyMethods(['getConfigErrors'])->getMock();
    $plugin->method('getConfigErrors')->willReturn(['Invalid source']);
    $form_state = (new FormState())->set('current_subform', 'data');
    $trigger = ['#parents' => ['actions', 'subforms', 'table']];
    $form_state->setTriggeringElement($trigger);
    $plugin->blockValidate([], $form_state);
    $this->assertSame([], $form_state->getErrors());
    $trigger = ['#parents' => ['actions', 'submit']];
    $form_state->setTriggeringElement($trigger);
    $plugin->blockValidate([], $form_state);
    $this->assertSame(['data' => 'Invalid source'], $form_state->getErrors());
    $form_state->clearErrors();
  }

  /**
   * Attachment context follows plugin classes, including newly registered IDs.
   */
  public function testAttachmentCapability(): void {
    $method = new \ReflectionMethod(PlanGoverningEntitiesOverviewTable::class, 'hasAttachmentColumns');
    $method->setAccessible(TRUE);
    foreach (['data_point', 'spark_line_chart', 'monitoring_period', 'custom_attachment'] as $id) {
      $plugin = $this->mockOverviewTable([['item_type' => $id]]);
      $this->assertTrue($method->invoke($plugin), $id);
    }
    foreach (['entity_name', 'funding_data', 'missing_plugin'] as $id) {
      $plugin = $this->mockOverviewTable([['item_type' => $id]]);
      $this->assertFalse($method->invoke($plugin), $id);
    }

    $columns = $this->createColumns();
    $columns[1]['item_type'] = 'custom_attachment';
    $plugin = $this->mockOverviewTable($columns);
    $cacheability = new CacheableMetadata();
    $rows = $this->buildRows($plugin, $columns, [$this->mockCaseloadWithValue(20)], $cacheability);
    $this->assertSame(['Protection', 20, 100], array_column($rows[0]['data'], 'export_value'));
  }

  /**
   * AJAX item-editor values can coexist with configured column entries.
   */
  public function testAttachmentCapabilityWithIncompleteFormValues(): void {
    $columns = [
      'item_config' => ['label' => 'People targeted'],
      'add_item' => 'Add new column',
      0 => [],
      1 => ['item_type' => NULL],
      2 => ['item_type' => ''],
      3 => ['item_type' => 'entity_name'],
    ];
    $method = new \ReflectionMethod(PlanGoverningEntitiesOverviewTable::class, 'hasAttachmentColumns');
    $method->setAccessible(TRUE);
    $this->assertFalse($method->invoke($this->mockOverviewTable($columns)));
    $columns[] = ['item_type' => 'custom_attachment'];
    $this->assertTrue($method->invoke($this->mockOverviewTable($columns)));
  }

  /**
   * Mock discovered classes without constructing any item plugins.
   */
  private function mockItemManagerWithDefinitions(): ConfigurationContainerItemManager {
    $classes = [
      'data_point' => DataPoint::class,
      'spark_line_chart' => SparkLineChart::class,
      'monitoring_period' => MonitoringPeriod::class,
      'custom_attachment' => DataPoint::class,
      'entity_name' => ConfigurationContainerItemPluginBase::class,
      'funding_data' => ConfigurationContainerItemPluginBase::class,
    ];
    $manager = $this->createMock(ConfigurationContainerItemManager::class);
    $manager->expects($this->never())->method('createInstance');
    $manager->method('getDefinition')->willReturnCallback(fn ($id) => isset($classes[$id]) ? ['class' => $classes[$id]] : NULL);
    return $manager;
  }

  /**
   * Create a mixed column configuration.
   */
  private function createColumns(): array {
    return [
      ['item_type' => 'entity_name', 'config' => []],
      ['item_type' => 'data_point', 'config' => []],
      ['item_type' => 'funding_data', 'config' => []],
    ];
  }

  /**
   * Mock a caseload with a predictable description and numeric value.
   */
  private function mockCaseloadWithValue(int $value): CaseloadAttachment {
    $attachment = $this->createMock(CaseloadAttachment::class);
    $attachment->method('id')->willReturn($value);
    $attachment->method('getDescription')->willReturn('Caseload ' . $value);
    return $attachment;
  }

  /**
   * Mock column values while retaining the actual table composition logic.
   */
  private function mockOverviewTable(array $columns): PlanGoverningEntitiesOverviewTable {
    $methods = ['getBlockConfig', 'getItemTypePluginForColumn', 'getConfigurationContainerItemManager'];
    $plugin = $this->getMockBuilder(PlanGoverningEntitiesOverviewTable::class)->disableOriginalConstructor()->onlyMethods($methods)->getMock();
    $config = ['table' => ['columns' => $columns], 'data' => []];
    $plugin->method('getBlockConfig')->willReturn($config);
    $plugin->method('getConfigurationContainerItemManager')->willReturn($this->mockItemManagerWithDefinitions());
    $plugin->method('getItemTypePluginForColumn')->willReturnCallback(function (array $column, array $context) {
      $type = $column['item_type'];
      $value = match ($type) {
        'entity_name' => 'Protection',
        'data_point', 'custom_attachment' => $context['attachment']?->id(),
        'funding_data' => 100,
      };
      $item_class = in_array($type, ['data_point', 'custom_attachment']) ? DataPoint::class : ConfigurationContainerItemPluginBase::class;
      $item = $this->createMock($item_class);
      $item->method('getColumnType')->willReturn($type == 'entity_name' ? 'name' : 'amount');
      $item->method('getClasses')->willReturn([$type]);
      $item->method('getCacheTags')->willReturn([$type == 'funding_data' ? 'funding:test' : 'caseload:' . $value]);
      $item->method('getTableCell')->willReturn([
        'data' => ['#plain_text' => (string) $value, '#cache' => ['max-age' => $type == 'funding_data' ? 0 : 3600]],
        'export_value' => $value,
        'class' => [$type],
      ]);
      $item->method('checkFilter')->willReturn(!isset($column['config']['filter_min']) || $value >= $column['config']['filter_min']);
      return $item;
    });
    return $plugin;
  }

  /**
   * Invoke the row composer with controlled data and shared cacheability.
   */
  private function buildRows(PlanGoverningEntitiesOverviewTable $plugin, array $columns, array $attachments, CacheableMetadata &$cacheability): array {
    $entity = $this->createMock(GoverningEntity::class);
    $entity->method('id')->willReturn(1);
    $method = new \ReflectionMethod($plugin, 'buildEntityRows');
    $method->setAccessible(TRUE);
    $context = ['entity' => $entity, 'attachment' => NULL];
    return $method->invokeArgs($plugin, [$columns, $context, $attachments, &$cacheability]);
  }

}
