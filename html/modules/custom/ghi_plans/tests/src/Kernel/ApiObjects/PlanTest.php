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
      'StartDate' => '2019-01-01',
      'EndDate' => '2019-12-31',
      'DocumentPublishDate' => NULL,
      // Seeting some properties to NULL so that no additional queries are
      // triggered.
      'FocusCountry' => NULL,
      'PlanType' => NULL,
      'PlanCosting' => NULL,
    ];

    $merged_overrides = array_merge($plan_defaults, $data_overrides);
    return parent::createMockRawData($merged_overrides);
  }

  /**
   * Test Plan constructor and mapping.
   */
  public function testPlanConstructorAndMapping(): void {
    $raw_data = $this->createMockRawData([
      'Id' => 123,
      'PlanSubTitle' => 'Afghanistan HRP',
      'Description' => 'This is a comment',
      'StartDate' => '2025-01-01T00:00:00.000Z',
      'EndDate' => '2025-12-31T00:00:00.000Z',
      'CreatedAt' => '2024-10-07T11:40:06.000Z',
      'UpdatedAt' => '2025-12-08T13:34:53.000Z',
      'planPeriod' => (object) ['items' => [(object) ['period' => (object) ['CalendarYear' => 2025]]]],
      'DocumentPublishDate' => NULL,
      'CurrentReportingPeriodId' => 1235,
      'LastPublishedReportingPeriodId' => 1234,
    ]);

    $plan = new Plan($raw_data);

    $this->assertApiObjectBasics($plan, 'plan');

    // Test Plan-specific properties if they exist.
    $this->assertEquals(123, $plan->id());

    // Test bundle method (from former testGetBundleReturnsCorrectBundle).
    $this->assertEquals('plan', $plan->getBundle());
    $this->assertEquals('Plan', (string) $plan->getTypeName());
    $this->assertEquals('PL', $plan->getEntityTypeRefCode());
    $this->assertEquals('plan', $plan->getEntityType());
    $this->assertEquals('Plan', $plan->getEntityTypeName());
    $this->assertEquals(2025, $plan->getYear());
    $this->assertEquals('Afghanistan HRP', $plan->getSubtitle());
    $this->assertEquals($plan->getName(), $plan->getDescription());
    $this->assertEquals('This is a comment', $plan->getComments());
    $this->assertEquals('2025-01-01', $plan->getStartDate());
    $this->assertEquals('2025-12-31', $plan->getEndDate());
    $this->assertEquals(1728301206, $plan->getCreatedDate());
    $this->assertEquals(1765200893, $plan->getUpdatedDate());
    $this->assertNull($plan->getDocumentPublishedDate());
    $this->assertEquals('en', $plan->getLanguageCode());
    $this->assertEquals(1234, $plan->getLastPublishedReportingPeriodId());
    $this->assertFalse($plan->isReleased());
    $this->assertFalse($plan->isRestricted());
    $this->assertFalse($plan->isPartOfGho());
  }

}
