<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\SparkLineChart;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the sparkline chart configuration item plugin.
 *
 * @group ghi_blocks
 */
class SparkLineChartTest extends UnitTestCase {

  /**
   * Tests that caseload chart data uses normalized period values.
   */
  public function testCaseloadSparklineDataUsesNormalizedReportingPeriodValues(): void {
    $reporting_periods = [
      487 => $this->createReportingPeriod(487),
      488 => $this->createReportingPeriod(488),
      489 => $this->createReportingPeriod(489),
    ];
    $expected_values = [
      487 => 801624,
      488 => NULL,
      489 => 2821190,
    ];

    $attachment = $this->getMockBuilder(Attachment::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getValuesForAllReportingPeriods'])
      ->getMock();
    $attachment->expects($this->once())
      ->method('getValuesForAllReportingPeriods')
      ->with('periodical_reach', FALSE, TRUE, $reporting_periods)
      ->willReturn([
        487 => 801624,
        489 => 2821190,
      ]);

    $chart = new SparkLineChart([], 'spark_line_chart', []);
    $method = new \ReflectionMethod($chart, 'getSparklineData');
    $method->setAccessible(TRUE);

    $this->assertSame($expected_values, $method->invoke($chart, $attachment, 'periodical_reach', $reporting_periods, FALSE));
  }

  /**
   * Tests that baseline values use normalized metric lookup.
   */
  public function testBaselineValueUsesNormalizedMetricLookup(): void {
    $attachment = $this->getMockBuilder(Attachment::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getValueByMetricType'])
      ->getMock();
    $attachment->expects($this->once())
      ->method('getValueByMetricType')
      ->with('target', 489)
      ->willReturn(4000000);

    $chart = new SparkLineChart([], 'spark_line_chart', []);
    $method = new \ReflectionMethod($chart, 'getBaselineValue');
    $method->setAccessible(TRUE);

    $this->assertSame(4000000, $method->invoke($chart, $attachment, 'target', 489));
  }

  /**
   * Tests that legacy baseline indexes resolve to metric types.
   */
  public function testLegacyBaselineIndexResolvesToMetricType(): void {
    $prototype = $this->getMockBuilder(AttachmentPrototype::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getMetricTypeByOriginalIndex'])
      ->getMock();
    $prototype->expects($this->once())
      ->method('getMetricTypeByOriginalIndex')
      ->with(2)
      ->willReturn('cumulative_reach');

    $attachment = $this->getMockBuilder(Attachment::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getPrototype'])
      ->getMock();
    $attachment->expects($this->once())
      ->method('getPrototype')
      ->willReturn($prototype);

    $chart = new SparkLineChart([], 'spark_line_chart', []);
    $chart->setConfig(['baseline' => 2]);
    $chart->setContext(['attachment' => $attachment]);
    $method = new \ReflectionMethod($chart, 'getConfiguredMetricType');
    $method->setAccessible(TRUE);

    $this->assertSame('cumulative_reach', $method->invoke($chart, 'baseline'));
  }

  /**
   * Creates a minimal reporting period object.
   *
   * @param int $id
   *   The reporting period id.
   *
   * @return object
   *   The reporting period object.
   */
  private function createReportingPeriod(int $id): object {
    return new class($id) {

      /**
       * Constructs a test reporting period.
       *
       * @param int $id
       *   The reporting period id.
       */
      public function __construct(private readonly int $id) {}

      /**
       * Gets the reporting period id.
       *
       * @return int
       *   The reporting period id.
       */
      public function id(): int {
        return $this->id;
      }

    };
  }

}
