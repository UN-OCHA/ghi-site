<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\ghi_plans\ApiObjects\Facts\GhoFact;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the GHO fact API object.
 */
#[Group('ghi_plans')]
class GhoFactTest extends UnitTestCase {

  /**
   * Tests fact values and dimensional references.
   */
  public function testFact(): void {
    $fact = new GhoFact((object) [
      'Id' => 1,
      'PlanId' => 10,
      'PeriodId' => 20,
      'LocationId' => 30,
      'SectorId' => 40,
      'MetricTypeId' => 50,
      'AggregationTypeId' => 2,
      'RevisionStateId' => GhoFact::GHO_REVISION_STATE_ID,
      'AsOfDate' => '2026-10-01',
      'ValueNum' => 125000,
      'Description' => 'Current GHO value',
      'metricType' => (object) [
        'Id' => 50,
        'Name' => 'People in need',
        'HPCType' => 'inNeed',
      ],
      'sector' => (object) [
        'Id' => 40,
        'Name' => 'Health',
        'EntityTypeId' => 60,
        'ReferenceCode' => 'HEA',
      ],
    ]);

    $this->assertSame(10, $fact->getPlanId());
    $this->assertSame(20, $fact->getPeriodId());
    $this->assertSame(30, $fact->getLocationId());
    $this->assertSame(40, $fact->getSectorId());
    $this->assertSame('in_need', $fact->getMetricName());
    $this->assertSame(2, $fact->getAggregationTypeId());
    $this->assertTrue($fact->isGhoRevision());
    $this->assertSame(125000.0, $fact->getValue());
    $this->assertSame('Health', $fact->getSectorName());
    $this->assertSame(60, $fact->getSectorEntityTypeId());
    $this->assertSame('HEA', $fact->getSectorReferenceCode());
  }

}
