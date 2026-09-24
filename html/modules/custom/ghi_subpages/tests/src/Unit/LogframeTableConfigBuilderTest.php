<?php

namespace Drupal\Tests\ghi_subpages\Unit;

use Drupal\ghi_subpages\Logframe\LogframeTableConfigBuilder;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the standard metrics shared by logframe provisioning and exports.
 *
 * @group ghi_subpages
 */
class LogframeTableConfigBuilderTest extends UnitTestCase {

  /**
   * Tests absent metrics and the valid zero-indexed target field.
   */
  public function testCaseloadMetricSelection(): void {
    $prototype = $this->createMock(AttachmentPrototype::class);
    $prototype->method('id')->willReturn(12);
    $prototype->method('isIndicator')->willReturn(FALSE);
    $prototype->method('getFieldTypes')->willReturn([0 => 'target', 1 => 'measure', 2 => 'measure']);
    $prototype->method('getMeasurementFields')->willReturn([1 => [], 2 => []]);
    $prototype->method('getDefaultFieldLabel')->with(2, 'en')->willReturn('Reached');

    $columns = $this->buildColumns($prototype);
    $this->assertCount(4, $columns);
    $this->assertSame(0, $columns[1]['config']['data_point']['data_points'][0]['index']);
    $this->assertSame(2, $columns[2]['config']['data_point']['data_points'][0]['index']);
    $this->assertSame('latest', $columns[2]['config']['data_point']['data_points'][0]['monitoring_period']);
    $this->assertSame('percentage', $columns[3]['config']['data_point']['calculation']);
    $this->assertSame(0, $columns[3]['config']['data_point']['data_points'][1]['index']);
  }

  /**
   * Tests that missing metrics do not silently resolve to field zero.
   */
  public function testCaseloadWithoutStandardMetrics(): void {
    $prototype = $this->createMock(AttachmentPrototype::class);
    $prototype->method('getFieldTypes')->willReturn([0 => 'other']);
    $prototype->method('getMeasurementFields')->willReturn([]);
    $columns = $this->buildColumns($prototype);
    $this->assertCount(1, $columns);
    $this->assertSame('attachment_label', $columns[0]['item_type']);
  }

  /**
   * Tests measurement priority and the last field within the preferred type.
   */
  public function testIndicatorMeasurementPriority(): void {
    $prototype = $this->createMock(AttachmentPrototype::class);
    $prototype->method('isIndicator')->willReturn(TRUE);
    $prototype->method('getFieldTypes')->willReturn([
      0 => 'target',
      1 => 'periodical_measure',
      2 => 'measure',
      3 => 'periodical_measure',
      4 => 'cumulative_measure',
    ]);
    $columns = $this->buildColumns($prototype);
    $this->assertCount(5, $columns);
    $this->assertSame(0, $columns[2]['config']['data_point']['data_points'][0]['index']);
    $this->assertSame(3, $columns[3]['config']['data_point']['data_points'][0]['index']);
    $this->assertSame('1', $columns[3]['config']['data_point']['data_points'][0]['use_calculation_method']);
    $this->assertSame('spark_line_chart', $columns[4]['item_type']);
    $this->assertSame(3, $columns[4]['config']['data_point']);
    $this->assertSame(0, $columns[4]['config']['baseline']);
  }

  /**
   * Tests that the first field can be a measurement.
   */
  public function testIndicatorMeasurementAtIndexZero(): void {
    $prototype = $this->createMock(AttachmentPrototype::class);
    $prototype->method('isIndicator')->willReturn(TRUE);
    $prototype->method('getFieldTypes')->willReturn([0 => 'periodical_measure', 1 => 'target', 2 => 'measure']);
    $columns = $this->buildColumns($prototype);
    $this->assertSame(0, $columns[3]['config']['data_point']['data_points'][0]['index']);
    $this->assertSame(0, $columns[4]['config']['data_point']);
  }

  /**
   * Builds the actual columns for the supplied prototype.
   */
  private function buildColumns(AttachmentPrototype $prototype): array {
    $plan = $this->createMock(Plan::class);
    $plan->method('getPlanLanguage')->willReturn('en');
    $builder = new LogframeTableConfigBuilder($this->getStringTranslationStub());
    return $builder->build($prototype, $plan)['config']['table_form']['columns'];
  }

}
