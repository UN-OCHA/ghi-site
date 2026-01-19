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
      'Id' => 123,
      'Name' => 'Test Plan Overview',
      'requirements' => 1500000,
    ]);

    $plan_overview = new PlanOverviewPlan($raw_data);

    $this->assertApiObjectBasics($plan_overview, 'plan', [
      'id',
      'name',
      'funding',
      'requirements',
      'coverage',
    ]);

    $this->assertEquals(1500000, $plan_overview->getRequirements());
    $this->assertEquals(66.7, $plan_overview->getCoverage(1000000));

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
      'PlanType' => 'Humanitarian response plan',
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
      'Id' => 1,
      'Name' => '',
      'requirements' => NULL,
    ]);
    $plan_overview = new PlanOverviewPlan($minimal_data);

    $this->assertEquals(1, $plan_overview->id());
    $this->assertIsString($plan_overview->getName());
    $this->assertEquals(0, $plan_overview->getRequirements());
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
