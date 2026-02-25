<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewCaseload;

/**
 * Tests the PlanOverviewCaseload API object.
 *
 * @group ghi_plans
 */
class PlanOverviewCaseloadTest extends ApiObjectTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createMockRawData(array $data_overrides = []): object {
    $plan_overview_caseload_defaults = [
      'Id' => rand(1, 100),
      'CustomReference' => $this->randomString(),
      'totals' => [],
    ];

    return (object) array_merge($plan_overview_caseload_defaults, $data_overrides);
  }

  /**
   * Test PlanOverviewCaseload constructor and mapping.
   */
  public function testPlanOverviewCaseloadConstructorAndMapping(): void {
    $raw_data = $this->createMockRawData([
      'Id' => 123,
      'PlanId' => 1,
      'CustomReference' => 'REF001',
    ]);

    $caseload = new PlanOverviewCaseload($raw_data);

    $this->assertEquals(123, $caseload->id());
    $this->assertNull($caseload->getTitle());
    $this->assertNull($caseload->getDescription());
    $this->assertEquals('REF001', $caseload->getCustomId());
    $this->assertEquals('PLREF001', $caseload->getCustomIdWithRefCode());
    $this->assertEquals('PLREF001', $caseload->getComposedReference());
    $this->assertIsArray($caseload->getFields());
    $this->assertIsArray($caseload->getFieldTypes());
    $this->assertEquals(1, $caseload->getPlanId());
    $this->assertEquals('plan', $caseload->getSourceEntityType());
    $this->assertEquals(1, $caseload->getSourceEntityId());

    // Test bundle method (from former testGetBundleReturnsCorrectBundle).
    $this->assertEquals('planoverviewcaseload', $caseload->getBundle());
  }

  /**
   * Test calculated fields handling.
   */
  public function testCalculatedFieldsHandling(): void {
    // Test with calculated fields as array.
    $raw_data = $this->createMockRawData([
      'PlanId' => 1,
      'totals' => [],
      'calculatedFields' => [
        (object) [
          'type' => 'calc1',
          'value' => 100,
        ],
        (object) [
          'type' => 'calc2',
          'value' => 200,
        ],
      ],
    ]);
    $caseload = new PlanOverviewCaseload($raw_data);

    $this->assertIsArray($caseload->getFields());
    $this->assertIsArray($caseload->getFieldTypes());
    $this->assertEquals(['calc1', 'calc2'], $caseload->getFieldTypes());

    // Test with calculated fields as object.
    $raw_data = $this->createMockRawData([
      'PlanId' => 1,
      'totals' => [],
      'calculatedFields' => (object) [
        'type' => 'calc1',
        'value' => 100,
      ],
    ]);
    $caseload = new PlanOverviewCaseload($raw_data);

    $this->assertIsArray($caseload->getFields());
    $this->assertIsArray($caseload->getFieldTypes());
    $this->assertEquals(['calc1'], $caseload->getFieldTypes());
  }

}
