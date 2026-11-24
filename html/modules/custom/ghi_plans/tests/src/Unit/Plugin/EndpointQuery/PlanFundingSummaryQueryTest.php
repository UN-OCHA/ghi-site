<?php

namespace Drupal\Tests\ghi_plans\Unit\Plugin\EndpointQuery;

use Drupal\Core\Cache\NullBackend;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ghi_plans\Plugin\EndpointQuery\PlanFundingSummaryQuery;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the plan funding summary endpoint query.
 */
#[CoversMethod(PlanFundingSummaryQuery::class, 'getDataForPlans')]
#[Group('ghi_plans')]
class PlanFundingSummaryQueryTest extends UnitTestCase {

  /**
   * Tests that plan summaries are prefetched with plan-specific cache tags.
   */
  public function testGetDataForPlans(): void {
    $endpoint_query = $this->createMock(EndpointQuery::class);
    $endpoint_query->method('getPlaceholders')->willReturnOnConsecutiveCalls(['plan_id' => 1], ['plan_id' => 2]);
    $endpoint_query->method('getFullEndpointUrl')->willReturnOnConsecutiveCalls('https://example.com/plan/1', 'https://example.com/plan/2');
    $endpoint_query->method('getData')->willReturnOnConsecutiveCalls(
      ['total_funding' => 10, 'overall_funding' => 20],
      ['total_funding' => 30, 'overall_funding' => 40],
    );
    $endpoint_query->expects($this->once())->method('queryPool')->with(
      [
        'https://example.com/plan/1',
        'https://example.com/plan/2',
      ],
      [
        'https://example.com/plan/1' => ['plan_id:1', 'plan_data'],
        'https://example.com/plan/2' => ['plan_id:2', 'plan_data'],
      ],
    );

    $query = new PlanFundingSummaryQuery(
      [],
      'plan_funding_summary_query',
      [
        'id' => 'plan_funding_summary_query',
        'endpoint' => [
          'public' => 'fts/flow/plan/summary/{plan_id}',
          'version' => 'v1',
        ],
      ],
      $endpoint_query,
      $this->createMock(AccountProxyInterface::class),
      new NullBackend('data'),
    );
    $this->assertSame([
      1 => ['total_funding' => 10, 'overall_funding' => 20],
      2 => ['total_funding' => 30, 'overall_funding' => 40],
    ], $query->getDataForPlans([1, 2, 2, 0]));
  }

}
