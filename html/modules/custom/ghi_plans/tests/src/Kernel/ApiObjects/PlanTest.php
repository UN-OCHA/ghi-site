<?php

namespace Drupal\Tests\ghi_plans\Kernel\ApiObjects;

use Drupal\ghi_plans\ApiObjects\Plan;

/**
 * Tests the Plan API object.
 *
 * @group ghi_plans
 */
class PlanTest extends PlanApiObjectKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createMockRawData(array $data_overrides = []): object {
    $plan_defaults = [
      'categories' => [
        (object) [
          'group' => 'plantype',
          'name' => 'Humanitarian Response Plan',
        ],
      ],
      'planVersion' => (object) [
        'id' => rand(1, 100),
        'name' => 'Plan Version ' . $this->randomString(),
        'lastPublishedReportingPeriodId' => rand(1, 50),
      ],
    ];

    $merged_overrides = array_merge($plan_defaults, $data_overrides);
    return parent::createMockRawData($merged_overrides);
  }

  /**
   * Test Plan constructor and mapping.
   */
  public function testPlanConstructorAndMapping(): void {
    $raw_data = $this->createMockRawData([
      'id' => 123,
      'planVersion' => (object) [
        'id' => 456,
        'name' => 'Version 1.0',
        'lastPublishedReportingPeriodId' => 1,
      ],
    ]);

    $plan = new Plan($raw_data);

    $this->assertApiObjectBasics($plan, 'plan');

    // Test Plan-specific properties if they exist.
    $this->assertEquals(123, $plan->id());
    $this->assertEquals('Version 1.0', $plan->getName());

    // Test bundle method (from former testGetBundleReturnsCorrectBundle).
    $this->assertEquals('plan', $plan->getBundle());
  }

  /**
   * Test null or empty data handling.
   */
  public function testNullOrEmptyDataHandling(): void {
    $this->testNullEmptyDataHandling(Plan::class);
  }

  /**
   * Test cache tags and dependencies.
   */
  public function testCacheTagsAndDependencies(): void {
    $raw_data = $this->createMockRawData();
    $plan = new Plan($raw_data);

    $cache_tags = $plan->getCacheTags();
    $this->assertIsArray($cache_tags);
  }

}
