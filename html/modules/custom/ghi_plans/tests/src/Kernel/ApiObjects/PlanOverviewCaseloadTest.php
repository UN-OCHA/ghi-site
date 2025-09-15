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
      'totals' => (object) ['inNeed' => 10000, 'target' => 5000],
      'customReference' => 'REF001',
    ];

    $merged_overrides = array_merge($plan_overview_caseload_defaults, $data_overrides);
    return parent::createMockRawData($merged_overrides);
  }

  /**
   * Test PlanOverviewCaseload constructor and mapping.
   */
  public function testPlanOverviewCaseloadConstructorAndMapping(): void {
    $raw_data = $this->createMockRawData([
      'attachmentId' => 123,
      'customReference' => 'REF001',
      'totals' => [
        'total_population' => (object) [
          'type' => 'caseload',
          'value' => 1000000,
        ],
      ],
      'calculatedFields' => (object) [
        'type' => 'calculated',
        'value' => 500000,
      ],
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
    $this->assertIsArray($caseload->original_fields);
    $this->assertIsArray($caseload->original_field_types);

    // Test bundle method (from former testGetBundleReturnsCorrectBundle).
    $this->assertEquals('planoverviewcaseload', $caseload->getBundle());
  }

  /**
   * Test null or empty data handling.
   */
  public function testNullOrEmptyDataHandling(): void {
    // Test with minimal data.
    $minimal_data = (object) [
      'attachmentId' => 1,
      'customReference' => 'REF001',
      'name' => '',
      'totals' => [],
      'calculatedFields' => NULL,
    ];
    $caseload = new PlanOverviewCaseload($minimal_data);

    $this->assertEquals(1, $caseload->id());
    $this->assertIsString($caseload->getName());
    $this->assertIsArray($caseload->original_fields);
  }

  /**
   * Test cache tags and dependencies.
   */
  public function testCacheTagsAndDependencies(): void {
    $raw_data = $this->createMockRawData([
      'attachmentId' => 123,
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
      'attachmentId' => 123,
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

    $this->assertIsArray($caseload->original_fields);
    $this->assertIsArray($caseload->original_field_types);
    $this->assertEquals(['calc1', 'calc2'], $caseload->original_field_types);
  }

}
