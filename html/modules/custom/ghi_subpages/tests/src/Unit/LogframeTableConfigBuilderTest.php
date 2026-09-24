<?php

namespace Drupal\Tests\ghi_subpages\Unit;

use Drupal\ghi_subpages\Logframe\LogframeTableConfigBuilder;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the standard metrics shared by logframe provisioning and exports.
 *
 * @coversDefaultClass \Drupal\ghi_subpages\Logframe\LogframeTableConfigBuilder
 * @group ghi_subpages
 */
class LogframeTableConfigBuilderTest extends UnitTestCase {

  /**
   * Tests reached values and percentages with a real metric-keyed prototype.
   */
  public function testCaseloadMetricSelection(): void {
    $prototype = $this->createAttachmentPrototype('caseload', ['target' => 'Target'], ['cumulativeReach' => 'Reached']);

    $columns = $this->buildColumns($prototype);
    $this->assertCount(4, $columns);
    $this->assertSame('target', $columns[1]['config']['data_point']['data_points'][0]['metric_type']);
    $this->assertSame('cumulative_reach', $columns[2]['config']['data_point']['data_points'][0]['metric_type']);
    $this->assertSame('latest', $columns[2]['config']['data_point']['data_points'][0]['monitoring_period']);
    $this->assertSame('People reached %', $columns[3]['config']['label']);
    $this->assertSame('percentage', $columns[3]['config']['data_point']['calculation']);
    $this->assertSame('cumulative_reach', $columns[3]['config']['data_point']['data_points'][0]['metric_type']);
    $this->assertSame('target', $columns[3]['config']['data_point']['data_points'][1]['metric_type']);
  }

  /**
   * Tests that caseload columns prefer cumulative reach over source order.
   */
  public function testBuildCaseloadColumnsUsesMetricTypes(): void {
    $prototype = $this->createAttachmentPrototype('caseload', [
      'inNeed' => 'In need',
      'target' => 'Target',
    ], [
      'latestReach' => 'Latest reached',
      'periodicalReach' => 'Reached',
      'cumulativeReach' => 'Cumulative reached',
    ]);

    $columns = $this->buildColumns($prototype);
    $this->assertCount(5, $columns);
    $this->assertSame('in_need', $columns[1]['config']['data_point']['data_points'][0]['metric_type']);
    $this->assertSame('target', $columns[2]['config']['data_point']['data_points'][0]['metric_type']);
    $this->assertSame('cumulative_reach', $columns[3]['config']['data_point']['data_points'][0]['metric_type']);
    $this->assertSame('cumulative_reach', $columns[4]['config']['data_point']['data_points'][0]['metric_type']);
    $this->assertSame('target', $columns[4]['config']['data_point']['data_points'][1]['metric_type']);
  }

  /**
   * Tests that missing metrics do not silently resolve to the first field.
   */
  public function testCaseloadWithoutStandardMetrics(): void {
    $prototype = $this->createAttachmentPrototype('caseload', ['other' => 'Other'], []);
    $columns = $this->buildColumns($prototype);
    $this->assertCount(1, $columns);
    $this->assertSame('attachment_label', $columns[0]['item_type']);
  }

  /**
   * Tests indicator measurement priority and metric-based sparkline config.
   */
  public function testBuildIndicatorColumnsUsesMetricTypes(): void {
    $prototype = $this->createAttachmentPrototype('indicator', ['target' => 'Target'], [
      'cumulativeMeasure' => 'Cumulative measure',
      'measure' => 'Measure',
      'periodicalMeasure' => 'Periodical measure',
    ]);

    $columns = $this->buildColumns($prototype);
    $this->assertCount(5, $columns);
    $this->assertSame('target', $columns[2]['config']['data_point']['data_points'][0]['metric_type']);
    $this->assertSame('periodical_measure', $columns[3]['config']['data_point']['data_points'][0]['metric_type']);
    $this->assertSame('1', $columns[3]['config']['data_point']['data_points'][0]['use_calculation_method']);
    $this->assertSame('spark_line_chart', $columns[4]['item_type']);
    $this->assertSame('periodical_measure', $columns[4]['config']['data_point']);
    $this->assertSame('target', $columns[4]['config']['baseline']);
  }

  /**
   * Tests that the first field can be a measurement without a target.
   */
  public function testIndicatorMeasurementWithoutTarget(): void {
    $prototype = $this->createAttachmentPrototype('indicator', [], ['periodicalMeasure' => 'Measure']);
    $columns = $this->buildColumns($prototype);
    $this->assertCount(4, $columns);
    $this->assertSame('periodical_measure', $columns[2]['config']['data_point']['data_points'][0]['metric_type']);
    $this->assertSame('periodical_measure', $columns[3]['config']['data_point']);
    $this->assertNull($columns[3]['config']['baseline']);
  }

  /**
   * Creates a prototype using the same raw field structure as Fabric.
   */
  private function createAttachmentPrototype(string $type, array $planning_fields, array $measurement_fields): AttachmentPrototype {
    $map_fields = static fn (array $fields) => array_map(static fn (string $type, string $label) => (object) [
      'type' => $type,
      'name' => (object) ['en' => $label],
    ], array_keys($fields), array_values($fields));
    $prototype = new AttachmentPrototype((object) [
      'Id' => 12,
      'PlanId' => 1,
      'RefCode' => 'TEST',
      'Type' => $type,
      'Value' => (object) [
        'metrics' => $map_fields($planning_fields),
        'measureFields' => $map_fields($measurement_fields),
      ],
    ]);
    $prototype->setStringTranslation($this->getStringTranslationStub());
    return $prototype;
  }

  /**
   * Builds the actual columns for the supplied prototype.
   */
  private function buildColumns(AttachmentPrototype $prototype): array {
    $plan = $this->createMock(Plan::class);
    $plan->method('getPlanLanguage')->willReturn('en');
    $builder = new LogframeTableConfigBuilder($this->getStringTranslationStub());
    $columns = $builder->build($prototype, $plan)['config']['table_form']['columns'];
    $this->assertStringNotContainsString('"index"', json_encode($columns));
    return $columns;
  }

}
