<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\ghi_blocks\Plugin\Block\Plan\PlanGoverningEntitiesTable;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\FundingData;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Plugin\EndpointQuery\FlowSearchQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\GoverningEntityQuery;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests cluster restriction with block and configuration item contexts.
 */
#[Group('ghi_blocks')]
class ConfigurationItemClusterRestrictTraitTest extends UnitTestCase {

  /**
   * Tests the common context API and unchanged cluster filtering.
   */
  #[DataProvider('clusterRestrictionProvider')]
  public function testClusterRestriction(string $class, string $type, bool $has_plan, ?array $expected): void {
    $plan = $has_plan ? $this->createMock(Plan::class) : NULL;
    if ($plan) {
      $plan->method('getSourceId')->willReturn(42);
    }
    $consumer = $this->getMockBuilder($class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getContextValue'])
      ->getMock();
    $consumer->expects($type === 'none' ? $this->never() : $this->once())
      ->method('getContextValue')->with('plan_object')->willReturn($plan);

    $cluster_query = $this->createMock(GoverningEntityQuery::class);
    $flow_query = $this->createMock(FlowSearchQuery::class);
    $queries_expected = $has_plan && $type !== 'none';
    $cluster_query->expects($queries_expected ? $this->once() : $this->never())
      ->method('getTaggedClustersForPlan')->with(42, 'example')->willReturn([2 => new \stdClass()]);
    $flow_query->expects($queries_expected ? $this->once() : $this->never())
      ->method('getClusterIds')->willReturn([1, 2, 3]);

    $this->assertSame($expected, $consumer->getClusterIdsByClusterRestrict(['type' => $type, 'tag' => 'example'], $cluster_query, $flow_query));
  }

  /**
   * Provides both consumer types with enabled and disabled restrictions.
   */
  public static function clusterRestrictionProvider(): array {
    $cases = [];
    foreach ([PlanGoverningEntitiesTable::class, FundingData::class] as $class) {
      $cases[$class . ': include'] = [$class, 'tag_include', TRUE, [1 => 2]];
      $cases[$class . ': exclude'] = [$class, 'tag_exclude', TRUE, [0 => 1, 2 => 3]];
      $cases[$class . ': unrestricted'] = [$class, 'none', TRUE, NULL];
      $cases[$class . ': no plan'] = [$class, 'tag_include', FALSE, NULL];
    }
    return $cases;
  }

}
