<?php

namespace Drupal\Tests\ghi_plans\Kernel\ApiObjects;

use Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewCaseload;

/**
 * Tests the PlanOverviewCaseload API object.
 *
 * @group ghi_plans
 */
class PlanOverviewCaseloadTest extends PlanApiObjectKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createMockRawData(array $data_overrides = []): object {
    $plan_overview_caseload_defaults = [
      'totals' => [],
      'CustomReference' => 'REF001',
    ];

    $merged_overrides = array_merge($plan_overview_caseload_defaults, $data_overrides);
    return parent::createMockRawData($merged_overrides);
  }

  /**
   * Test PlanOverviewCaseload constructor and mapping.
   */
  public function testPlanOverviewCaseloadConstructorAndMapping(): void {
    $raw_data = $this->createMockRawData([
      'Id' => 123,
      'PlanId' => 1,
    ]);

    $caseload = new PlanOverviewCaseload($raw_data);

    $this->assertApiObjectBasics($caseload, 'planoverviewcaseload', [
      'id',
      'custom_id',
      'original_fields',
      'original_field_types',
    ]);

    $this->assertEquals(123, $caseload->id());
    $this->assertEquals('REF001', $caseload->custom_id);
    $this->assertIsArray($caseload->getOriginalFields());
    $this->assertIsArray($caseload->original_field_types);

    // Test bundle method (from former testGetBundleReturnsCorrectBundle).
    $this->assertEquals('planoverviewcaseload', $caseload->getBundle());
  }

  /**
   * Test cache tags and dependencies.
   */
  public function testCacheTagsAndDependencies(): void {
    $raw_data = $this->createMockRawData([
      'PlanId' => 1,
      'totals' => [],
    ]);
    $caseload = new PlanOverviewCaseload($raw_data);

    $cache_tags = $caseload->getCacheTags();
    $this->assertIsArray($cache_tags);

    $cache_contexts = $caseload->getCacheContexts();
    $this->assertIsArray($cache_contexts);

    $cache_max_age = $caseload->getCacheMaxAge();
    $this->assertIsInt($cache_max_age);
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

    $this->assertIsArray($caseload->getOriginalFields());
    $this->assertIsArray($caseload->getOriginalFieldTypes());
    $this->assertEquals(['calc1', 'calc2'], $caseload->getOriginalFieldTypes());
  }

}
