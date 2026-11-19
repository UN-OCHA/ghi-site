<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\ValueObject\PlanMetricValues;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests normalized plan metric value sets.
 */
#[Group('ghi_plans')]
class PlanMetricValuesTest extends UnitTestCase {

  /**
   * Tests that metric overrides preserve existing source metadata.
   */
  public function testMetricOverrides(): void {
    $base = new PlanMetricValues(
      Plan::DATA_SOURCE_PLAN,
      ['target' => 100, 'prioritized_target' => 80],
      [1 => 'base caseload'],
      ['base'],
      TRUE,
      ['target' => Plan::DATA_SOURCE_GHO],
    );
    $overrides = new PlanMetricValues(
      Plan::DATA_SOURCE_GHO,
      ['prioritized_requirements' => 500],
      [2 => 'override caseload'],
      ['override'],
    );

    $result = $base->withMetricOverrides($overrides, Plan::PRIORITIZED_METRICS);

    $this->assertSame(100, $result->get('target'));
    $this->assertFalse($result->has('prioritized_target'));
    $this->assertSame(500, $result->get('prioritized_requirements'));
    $this->assertSame(Plan::DATA_SOURCE_GHO, $result->getSourceForMetric('target'));
    $this->assertSame(Plan::DATA_SOURCE_GHO, $result->getSourceForMetric('prioritized_target'));
    $this->assertSame([1 => 'base caseload', 2 => 'override caseload'], $result->getCaseloads());
    $this->assertSame(['base', 'override'], $result->getCacheTags());
    $this->assertTrue($result->hasSourceValues());
  }

}
