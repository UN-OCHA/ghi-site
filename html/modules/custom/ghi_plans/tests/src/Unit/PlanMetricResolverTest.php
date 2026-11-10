<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachmentInterface;
use Drupal\ghi_plans\ApiObjects\Facts\GhoFact;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\PlanMetricResolver;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentPrototypeQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\GhoFactQuery;
use Drupal\hpc_api\Query\FabricQueryManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests source-aware plan metric resolution.
 */
#[Group('ghi_plans')]
class PlanMetricResolverTest extends UnitTestCase {

  /**
   * Tests Country Module caseload and imported financial data.
   */
  public function testResolvePlanData(): void {
    $attachment_query = $this->createMock(AttachmentQuery::class);
    $prototype_query = $this->createMock(AttachmentPrototypeQuery::class);
    $gho_query = $this->createMock(GhoFactQuery::class);
    $resolver = $this->createResolver($attachment_query, $prototype_query, $gho_query);

    $caseload = $this->createMock(CaseloadAttachmentInterface::class);
    $caseload->method('getPlanId')->willReturn(10);
    $caseload->method('id')->willReturn(100);
    $caseload->method('getValueCacheTags')->willReturn(['attachment_id:100', 'plan_id:10']);
    $caseload->method('getCaseloadValue')->willReturnMap([
      ['total_population', 'Population', 1000],
      ['in_need', NULL, 500],
      ['target', NULL, 300],
      ['expected_reach', 'Expected Reach', 250],
      ['latest_reach', NULL, 200],
    ]);
    $attachment_query->expects($this->once())
      ->method('getAttachmentsByObject')
      ->with(PlanEntityInterface::ENTITY_TYPE_PLAN, [10], 'caseload')
      ->willReturn([$caseload]);

    $plan = $this->createMock(Plan::class);
    $plan->method('getPlanDataSource')->willReturn(Plan::DATA_SOURCE_PLAN);
    $plan->method('getPlanCaseload')->willReturn($caseload);
    $plan->method('getRequirements')->willReturn(500000.0);
    $plan->method('getOriginalRequirements')->willReturn(550000.0);
    $plan->method('getTotalFunding')->willReturn(250000.0);
    $plan->method('getOverallFunding')->willReturn(300000.0);

    $values = $resolver->resolve([10 => $plan])[10];
    $this->assertSame(Plan::DATA_SOURCE_PLAN, $values->getSource());
    $this->assertSame(500.0, $values->get('in_need'));
    $this->assertSame(300.0, $values->get('target'));
    $this->assertSame(500000.0, $values->get('requirements'));
    $this->assertSame(550000.0, $values->get('original_requirements'));
    $this->assertSame(250000.0, $values->get('funding'));
    $this->assertSame(50000.0, $values->get('outside_funding'));
    $this->assertSame([100 => $caseload], $values->getCaseloads());
    $this->assertSame(['plan_id:10', 'attachment_id:100'], $values->getCacheTags());
  }

  /**
   * Tests the historical cumulative reach fallback.
   */
  public function testResolvePlanDataHistoricalReachFallback(): void {
    $attachment_query = $this->createMock(AttachmentQuery::class);
    $prototype_query = $this->createMock(AttachmentPrototypeQuery::class);
    $gho_query = $this->createMock(GhoFactQuery::class);
    $resolver = $this->createResolver($attachment_query, $prototype_query, $gho_query);

    $caseload = $this->createMock(CaseloadAttachmentInterface::class);
    $caseload->method('getPlanId')->willReturn(10);
    $caseload->method('id')->willReturn(100);
    $caseload->method('getCaseloadValue')->willReturnMap([
      ['latest_reach', NULL, NULL],
      ['cumulative_reach', NULL, 175.0],
    ]);
    $attachment_query->method('getAttachmentsByObject')->willReturn([$caseload]);

    $plan = $this->createMock(Plan::class);
    $plan->method('getPlanDataSource')->willReturn(Plan::DATA_SOURCE_PLAN);
    $plan->method('getPlanCaseload')->willReturn($caseload);
    $plan->method('getRequirements')->willReturn(500000.0);

    $values = $resolver->resolve([10 => $plan])[10];
    $this->assertSame(175.0, $values->get('latest_reach'));
    $this->assertSame(500000.0, $values->get('requirements'));
  }

  /**
   * Tests latest, plan-level GHO fact selection without source fallback.
   */
  public function testResolveGhoData(): void {
    $attachment_query = $this->createMock(AttachmentQuery::class);
    $prototype_query = $this->createMock(AttachmentPrototypeQuery::class);
    $gho_query = $this->createMock(GhoFactQuery::class);
    $resolver = $this->createResolver($attachment_query, $prototype_query, $gho_query);

    $facts = [
      $this->mockFact('in_need', 400, '2026-10-01'),
      $this->mockFact('in_need', 350, '2026-09-01'),
      $this->mockFact('requirements', 900000, '2026-10-01'),
      $this->mockFact('target', 200, '2026-10-01'),
      $this->mockFact('target', 250, '2026-10-01'),
      $this->mockFact('funding', 100000, '2026-10-01', 5),
    ];
    $gho_query->method('getFactsForPlans')->with([10])->willReturn([10 => $facts]);

    $plan = $this->createMock(Plan::class);
    $plan->method('getPlanDataSource')->willReturn(Plan::DATA_SOURCE_PLAN);
    $values = $resolver->resolve([10 => $plan], Plan::DATA_SOURCE_GHO)[10];

    $this->assertSame(Plan::DATA_SOURCE_GHO, $values->getSource());
    $this->assertSame(400.0, $values->get('in_need'));
    $this->assertSame(900000.0, $values->get('requirements'));
    $this->assertFalse($values->has('target'));
    $this->assertFalse($values->has('funding'));
    $this->assertSame(['plan_id:10'], $values->getCacheTags());
  }

  /**
   * Creates the resolver with controlled query plugins.
   */
  private function createResolver(AttachmentQuery $attachment_query, AttachmentPrototypeQuery $prototype_query, GhoFactQuery $gho_query): PlanMetricResolver {
    $manager = $this->createMock(FabricQueryManager::class);
    $manager->method('createInstance')->willReturnCallback(fn (string $plugin_id) => match ($plugin_id) {
      'attachment' => $attachment_query,
      'attachment_prototype' => $prototype_query,
      'gho_fact' => $gho_query,
    });
    return new PlanMetricResolver($manager);
  }

  /**
   * Mocks a controlled GHO fact.
   */
  private function mockFact(string $metric, float $value, string $as_of_date, ?int $sector_id = NULL): GhoFact {
    $fact = $this->createMock(GhoFact::class);
    $fact->method('isGhoRevision')->willReturn(TRUE);
    $fact->method('getSectorId')->willReturn($sector_id);
    $fact->method('getMetricName')->willReturn($metric);
    $fact->method('getValue')->willReturn($value);
    $fact->method('getAsOfDate')->willReturn($as_of_date);
    return $fact;
  }

}
