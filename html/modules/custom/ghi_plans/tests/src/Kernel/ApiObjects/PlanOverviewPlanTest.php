<?php

namespace Drupal\Tests\ghi_plans\Kernel\ApiObjects;

use Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan;

/**
 * Tests the PlanOverviewPlan API object.
 *
 * @group ghi_plans
 */
class PlanOverviewPlanTest extends PlanApiObjectKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('taxonomy_term');
  }

  /**
   * {@inheritdoc}
   */
  protected function createMockRawData(array $data_overrides = []): object {
    $plan_overview_plan_defaults = [
      'requirements' => (object) ['totalFunding' => 100000, 'fundingProgress' => 50000, 'revisedRequirements' => 75000],
    ];

    $merged_overrides = array_merge($plan_overview_plan_defaults, $data_overrides);
    return parent::createMockRawData($merged_overrides);
  }

  /**
   * Test PlanOverviewPlan constructor and mapping.
   */
  public function testPlanOverviewPlanConstructorAndMapping(): void {
    $raw_data = $this->createMockRawData([
      'id' => 123,
      'name' => 'Test Plan Overview',
      'funding' => (object) [
        'totalFunding' => 1000000,
        'progress' => 75.5,
      ],
      'requirements' => (object) [
        'revisedRequirements' => 1500000,
      ],
    ]);

    $plan_overview = new PlanOverviewPlan($raw_data);

    $this->assertApiObjectBasics($plan_overview, 'plan', [
      'id',
      'name',
      'funding',
      'requirements',
      'coverage',
    ]);

    $this->assertEquals(1000000, $plan_overview->getFunding());
    $this->assertEquals(1500000, $plan_overview->getRequirements());
    $this->assertEquals(75.5, $plan_overview->getCoverage());

    $this->assertEquals('plan', $plan_overview->getBundle());
    $this->assertEquals('Test Plan Overview', $plan_overview->getName());

    // No entity in the tests, so no plan status nor label.
    $this->assertFalse($plan_overview->getPlanStatus());
    $this->assertNull($plan_overview->getPlanStatusLabel());
    $this->assertNull($plan_overview->getPlanDocumentUri());
    $this->assertNull($plan_overview->getPlanType());
    $this->assertNull($plan_overview->getTypeName());
    $this->assertNull($plan_overview->getTypeShortName());
    $this->assertNull($plan_overview->getTypeOrder());
    $this->assertFalse($plan_overview->isType('Humanitarian response plan'));
    $this->assertFalse($plan_overview->isHrp());
    $this->assertFalse($plan_overview->isRrp());
    $this->assertFalse($plan_overview->isFlashAppeal());
    $this->assertTrue($plan_overview->isOther());
    $this->assertFalse($plan_overview->isPartOfGho());
  }

  /**
   * Test PlanOverviewPlan plan types.
   */
  public function testPlanPlanTypes(): void {
    $raw_data = $this->createMockRawData([
      'planType' => (object) [
        'name' => 'Humanitarian response plan',
      ],
    ]);
    $plan_overview = new PlanOverviewPlan($raw_data);
    $this->assertNull($plan_overview->getPlanType());
    $this->assertEquals('Humanitarian response plan', $plan_overview->getTypeName());
    $this->assertEquals('HRP', $plan_overview->getTypeShortName());
    $this->assertTrue($plan_overview->isType('Humanitarian response plan'));
    $this->assertTrue($plan_overview->isHrp());
    $this->assertFalse($plan_overview->isRrp());
    $this->assertFalse($plan_overview->isFlashAppeal());
    $this->assertFalse($plan_overview->isOther());
    $this->assertFalse($plan_overview->isPartOfGho());
  }

  /**
   * Test null or empty data handling.
   */
  public function testNullOrEmptyDataHandling(): void {
    // Test with minimal data including null funding/requirements.
    $minimal_data = $this->createMockRawData([
      'id' => 1,
      'name' => '',
      'funding' => NULL,
      'requirements' => NULL,
    ]);
    $plan_overview = new PlanOverviewPlan($minimal_data);

    $this->assertEquals(1, $plan_overview->id());
    $this->assertIsString($plan_overview->getName());
    $this->assertEquals(0, $plan_overview->funding);
    $this->assertEquals(0, $plan_overview->requirements);
  }

  /**
   * Test invalid data structure handling.
   */
  public function testInvalidDataStructureHandling(): void {
    // Test with missing funding and requirements objects.
    $raw_data = $this->createMockRawData([
      'id' => 123,
      'name' => 'Test Plan',
      'funding' => NULL,
      'requirements' => NULL,
    ]);
    $plan_overview = new PlanOverviewPlan($raw_data);

    $this->assertEquals(123, $plan_overview->id());
    $this->assertEquals(0, $plan_overview->funding);
    $this->assertEquals(0, $plan_overview->requirements);
  }

  /**
   * Test cache tags and dependencies.
   */
  public function testCacheTagsAndDependencies(): void {
    $raw_data = $this->createMockRawData();
    $plan_overview = new PlanOverviewPlan($raw_data);

    $cache_tags = $plan_overview->getCacheTags();
    $this->assertIsArray($cache_tags);

    $cache_contexts = $plan_overview->getCacheContexts();
    $this->assertIsArray($cache_contexts);

    $cache_max_age = $plan_overview->getCacheMaxAge();
    $this->assertIsInt($cache_max_age);
  }

}
