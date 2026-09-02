<?php

namespace Drupal\Tests\ghi_plans\Kernel\ApiObjects;

use Drupal\ghi_plans\ApiObjects\PlanReportingPeriod;

/**
 * Tests the PlanReportingPeriod API object.
 *
 * @group ghi_plans
 */
class PlanReportingPeriodTest extends PlanApiObjectKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createMockRawData(array $data_overrides = []): object {
    $defaults = [
      'Id' => rand(1, 100),
      'PlanId' => rand(1, 100),
      'PeriodNumber' => rand(1, 4),
      'MeasurementsGenerated' => TRUE,
      'StartDate' => '2024-01-01',
      'EndDate' => '2024-03-31',
    ];

    $merged_overrides = array_merge($defaults, $data_overrides);
    return parent::createMockRawData($merged_overrides);
  }

  /**
   * Test PlanReportingPeriod constructor and mapping.
   */
  public function testPlanReportingPeriodConstructorAndMapping(): void {
    $raw_data = $this->createMockRawData([
      'Id' => 123,
    ]);
    $plan_reporting_period = new PlanReportingPeriod($raw_data);
    $this->assertApiObjectBasics($plan_reporting_period, 'planreportingperiod');
  }

}
