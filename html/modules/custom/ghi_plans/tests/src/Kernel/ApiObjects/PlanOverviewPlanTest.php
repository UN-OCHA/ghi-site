<?php

namespace Drupal\Tests\ghi_plans\Kernel\ApiObjects;

use Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachmentInterface;
use Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan;
use Drupal\ghi_plans\ApiObjects\Plan;
use Drupal\ghi_plans\Entity\Plan as PlanEntity;
use Drupal\ghi_plans\Plugin\FabricQuery\PlanQuery;
use Drupal\ghi_plans\ValueObject\PlanMetricValues;
use Drupal\hpc_api\ApiObjects\Types\PlanType;
use Drupal\hpc_api\Query\FabricQueryManager;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the PlanOverviewPlan API object.
 */
#[Group('ghi_plans')]
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
   * Test PlanOverviewPlan constructor and mapping.
   */
  public function testPlanOverviewPlanConstructorAndMapping(): void {
    $raw_data = $this->createMockRawData([
      'plan' => new Plan((object) [
        'Id' => 123,
        'Name' => 'Test Plan Overview',
        'Year' => 2025,
      ]),
      'requirements' => 1500000,
      'funding' => 1000000,
    ]);

    $plan_overview = new PlanOverviewPlan($raw_data);

    $this->assertApiObjectBasics($plan_overview, 'plan');

    $this->assertEquals(1500000, $plan_overview->getRequirements());
    $this->assertEquals(1000000, $plan_overview->getFunding());
    $this->assertEquals(66.7, $plan_overview->getCoverage());

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
   * Tests that attachment fallback follows the source selected per metric.
   */
  public function testMixedSourceCaseloadFallback(): void {
    $caseload = $this->createMock(CaseloadAttachmentInterface::class);
    $caseload->method('getFieldTypes')->willReturn(['inNeed']);
    $caseload->method('getCaseloadValue')->willReturnMap([
      ['target', NULL, 300],
      ['prioritized_target', NULL, 250],
    ]);
    $plan = new Plan((object) [
      'Id' => 123,
      'Name' => 'Test Plan Overview',
      'Year' => 2025,
    ]);

    $country_module_plan = new PlanOverviewPlan($this->createMockRawData([
      'plan' => $plan,
      'metrics' => new PlanMetricValues(
        PlanEntity::DATA_SOURCE_PLAN,
        [],
        [100 => $caseload],
        metricSources: ['prioritized_target' => PlanEntity::DATA_SOURCE_GHO],
      ),
    ]));
    $this->assertSame(300, $country_module_plan->getCaseloadValue('target'));
    $this->assertNull($country_module_plan->getCaseloadValue('prioritized_target'));

    $gho_plan = new PlanOverviewPlan($this->createMockRawData([
      'plan' => $plan,
      'metrics' => new PlanMetricValues(
        PlanEntity::DATA_SOURCE_GHO,
        [],
        [100 => $caseload],
        metricSources: ['prioritized_target' => PlanEntity::DATA_SOURCE_PLAN],
      ),
    ]));
    $this->assertNull($gho_plan->getCaseloadValue('target'));
    $this->assertSame(250, $gho_plan->getCaseloadValue('prioritized_target'));
  }

  /**
   * Test PlanOverviewPlan plan types.
   */
  public function testPlanPlanTypes(): void {
    $plan_type = $this->prophesize(PlanType::class);
    $plan_type->getName()->willReturn('Humanitarian response plan');
    $plan_query = $this->prophesize(PlanQuery::class);
    $plan_query->getPlanTypeByName('Humanitarian response plan')->willReturn($plan_type->reveal());
    $fabric_query_manager = $this->prophesize(FabricQueryManager::class);
    $fabric_query_manager->hasDefinition('plan')->willReturn(TRUE);
    $fabric_query_manager->createInstance('plan')->willReturn($plan_query->reveal());
    $this->container->set('plugin.manager.fabric_query_manager', $fabric_query_manager->reveal());

    $raw_data = $this->createMockRawData([
      'plan' => new Plan((object) [
        'Id' => 123,
        'Name' => 'Test Plan Overview',
        'Year' => 2025,
        'PlanType' => 'Humanitarian response plan',
      ]),
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

}
