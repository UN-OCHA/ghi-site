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
      'id' => rand(1, 100),
      'planId' => rand(1, 100),
      'periodNumber' => rand(1, 4),
      'measurementsGenerated' => TRUE,
      'startDate' => '2024-01-01',
      'endDate' => '2024-03-31',
    ];

    $merged_overrides = array_merge($defaults, $data_overrides);
    return parent::createMockRawData($merged_overrides);
  }

  /**
   * Test PlanReportingPeriod constructor and mapping.
   */
  public function testPlanReportingPeriodConstructorAndMapping(): void {
    $raw_data = $this->createMockRawData([
      'id' => 123,
    ]);

    $plan_reporting_period = new PlanReportingPeriod($raw_data);

    $this->assertApiObjectBasics($plan_reporting_period, 'planreportingperiod', [
      'id',
    ]);

    $this->assertEquals('planreportingperiod', $plan_reporting_period->getBundle());
  }

  /**
   * Test cache tags and dependencies.
   */
  public function testCacheTagsAndDependencies(): void {
    $raw_data = $this->createMockRawData();
    $plan_reporting_period = new PlanReportingPeriod($raw_data);

    $cache_tags = $plan_reporting_period->getCacheTags();
    $this->assertIsArray($cache_tags);

    $cache_contexts = $plan_reporting_period->getCacheContexts();
    $this->assertIsArray($cache_contexts);

    $cache_max_age = $plan_reporting_period->getCacheMaxAge();
    $this->assertIsInt($cache_max_age);
  }

}
