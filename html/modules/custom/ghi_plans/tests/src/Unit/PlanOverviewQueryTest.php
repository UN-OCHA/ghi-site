<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ghi_plans\ApiObjects\Plan;
use Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan;
use Drupal\ghi_plans\Entity\Plan as PlanEntity;
use Drupal\ghi_plans\PlanMetricResolver;
use Drupal\ghi_plans\Plugin\FabricQuery\PlanOverviewQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\PlanQuery;
use Drupal\ghi_plans\ValueObject\PlanMetricValues;
use Drupal\Tests\hpc_api\Traits\PrivateAccessorTrait;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests overview retrieval when Fabric plans have not been imported.
 */
#[Group('ghi_plans')]
class PlanOverviewQueryTest extends UnitTestCase {

  use PrivateAccessorTrait;

  /**
   * Tests aggregation of source-aware financial metrics.
   */
  public function testFinancialMetricTotals(): void {
    $first_aggregate_plan = $this->createMock(PlanOverviewPlan::class);
    $first_aggregate_plan->method('getRequirements')->willReturn(800);
    $first_aggregate_plan->method('getFunding')->willReturn(400);
    $first_aggregate_plan->method('getPrioritizedRequirements')->willReturn(450);
    $second_aggregate_plan = $this->createMock(PlanOverviewPlan::class);
    $second_aggregate_plan->method('getRequirements')->willReturn(200);
    $second_aggregate_plan->method('getFunding')->willReturn(NULL);
    $second_aggregate_plan->method('getPrioritizedRequirements')->willReturn(250);
    $visible_plans = [
      1 => $this->createMock(PlanOverviewPlan::class),
      2 => $this->createMock(PlanOverviewPlan::class),
    ];

    $query = $this->getMockBuilder(PlanOverviewQuery::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getGhoPlans'])
      ->getMock();
    $query->method('getGhoPlans')->willReturn($visible_plans);
    (new \ReflectionProperty(PlanOverviewQuery::class, 'aggregatePlans'))->setValue($query, [
      1 => $first_aggregate_plan,
      2 => $second_aggregate_plan,
    ]);

    $this->assertSame(1000.0, $query->getTotalRequirements());
    $this->assertSame(400.0, $query->getTotalFunding());
    $this->assertSame(700.0, $query->getTotalPrioritizedRequirements());
  }

  /**
   * Tests population totals use the aggregate-ready plan values.
   */
  public function testCaseloadMetricTotals(): void {
    $first_aggregate_plan = $this->createMock(PlanOverviewPlan::class);
    $first_aggregate_plan->method('id')->willReturn(1);
    $first_aggregate_plan->method('getPlanCaseloadFields')->willReturn([
      'in_need' => 100,
      'target' => 80,
      'latest_reach' => 60,
    ]);
    $first_aggregate_plan->method('getCaseloadValue')->willReturnMap([
      ['in_need', 'In Need', 100],
      ['target', 'Target', 80],
      ['latest_reach', 'Reached', 60],
    ]);
    $first_aggregate_plan->method('isRrp')->willReturn(FALSE);
    $second_aggregate_plan = $this->createMock(PlanOverviewPlan::class);
    $second_aggregate_plan->method('id')->willReturn(2);
    $second_aggregate_plan->method('getPlanCaseloadFields')->willReturn([
      'in_need' => 50,
      'target' => 40,
      'latest_reach' => 30,
    ]);
    $second_aggregate_plan->method('getCaseloadValue')->willReturnMap([
      ['in_need', 'In Need', 50],
      ['target', 'Target', 40],
      ['latest_reach', 'Reached', 30],
    ]);
    $second_aggregate_plan->method('isRrp')->willReturn(FALSE);
    $visible_plans = [
      1 => $this->createMock(PlanOverviewPlan::class),
      2 => $this->createMock(PlanOverviewPlan::class),
    ];

    $query = $this->getMockBuilder(PlanOverviewQuery::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getGhoPlans', 'getPlans'])
      ->getMock();
    $query->method('getGhoPlans')->willReturn($visible_plans);
    $query->method('getPlans')->willReturn($visible_plans);
    (new \ReflectionProperty(PlanOverviewQuery::class, 'aggregatePlans'))->setValue($query, [
      1 => $first_aggregate_plan,
      2 => $second_aggregate_plan,
    ]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([]);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with('base_object')->willReturn($storage);
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($container);

    $totals = $query->getCaseloadTotalValues([
      'in_need' => 'In Need',
      'target' => 'Target',
      'latest_reach' => 'Reached',
    ]);
    $this->assertSame(150, $totals['in_need']);
    $this->assertSame(120, $totals['target']);
    $this->assertSame(90, $totals['latest_reach']);
    $this->assertSame(120, $totals['target_custom']);
    $this->assertSame(90, $totals['reached_custom']);
  }

  /**
   * Tests that only imported plans are used for overview data.
   *
   * @param int[] $fabric_ids
   *   The plan IDs returned by Fabric.
   * @param int[] $imported_ids
   *   The plan IDs with matching Drupal entities.
   */
  #[DataProvider('planIdsProvider')]
  public function testOnlyImportedPlansAreRetrieved(array $fabric_ids, array $imported_ids): void {
    drupal_static_reset();
    $plans = [];
    foreach ($fabric_ids as $id) {
      $plan = $this->createMock(Plan::class);
      $plan->method('id')->willReturn($id);
      $plan->method('getName')->willReturn('Plan ' . $id);
      $plan->method('getPlanType')->willReturn(NULL);
      $plans[$id] = $plan;
    }

    $entities = [];
    foreach ($imported_ids as $id) {
      $entity = $this->createMock(PlanEntity::class);
      $entity->method('bundle')->willReturn('plan');
      $entity->method('getSourceId')->willReturn($id);
      $entity->method('getCacheTags')->willReturn(['plan:' . $id]);
      $entities[] = $entity;
    }

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($fabric_ids ? $this->once() : $this->never())
      ->method('loadByProperties')
      ->with([
        'type' => 'plan',
        'field_original_id' => array_combine($fabric_ids, $fabric_ids),
      ])
      ->willReturn($entities);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with('base_object')->willReturn($storage);
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($container);

    $plan_query = $this->createMock(PlanQuery::class);
    $plan_query->expects($this->once())->method('getPlansByYear')->with(2026)->willReturn($plans);
    $expected_entities = array_combine($imported_ids, $entities);
    $metric_values = [];
    foreach ($imported_ids as $imported_id) {
      $metric_values[$imported_id] = new PlanMetricValues(PlanEntity::DATA_SOURCE_PLAN, ['funding' => 123], cacheTags: ['plan_id:' . $imported_id]);
    }
    $metric_resolver = $this->createMock(PlanMetricResolver::class);
    $metric_resolver->expects($imported_ids ? $this->once() : $this->never())
      ->method('resolve')
      ->with($expected_entities)
      ->willReturn($metric_values);

    $query = new PlanOverviewQuery([], 'plan_overview', ['id' => 'plan_overview']);
    $this->setPrivateProperty($query, 'planQuery', $plan_query);
    $this->setPrivateProperty($query, 'planMetricResolver', $metric_resolver);
    $query->setYear(2026);

    // Disable visibility filtering to verify the import guard independently.
    $result = $query->getPlans(FALSE);
    $this->assertSame($imported_ids, array_keys($result));
    foreach ($result as $plan) {
      $this->assertSame(123, $plan->getFunding());
    }
    $expected_cache_tags = array_map(fn (int $id): string => 'plan:' . $id, $imported_ids);
    $expected_cache_tags = array_merge($expected_cache_tags, array_map(fn (int $id): string => 'plan_id:' . $id, $imported_ids));
    $this->assertSame($expected_cache_tags, $query->getCacheTags());
    $this->assertSame($result, $query->getPlans(FALSE));
  }

  /**
   * Provides combinations of remote and imported plans.
   *
   * @return array
   *   The plan IDs for each scenario.
   */
  public static function planIdsProvider(): array {
    return [
      'no Fabric plans' => [[], []],
      'no imported plans' => [[101, 102], []],
      'partially imported plans' => [[101, 102], [102]],
      'all plans imported' => [[101, 102], [101, 102]],
    ];
  }

}
