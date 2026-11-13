<?php

namespace Drupal\Tests\hpc_api\Unit;

use Drupal\hpc_api\ApiObjects\Types\AggregationType;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the aggregation type API object.
 */
#[CoversClass(AggregationType::class)]
class AggregationTypeTest extends UnitTestCase {

  /**
   * Tests the semantic aggregation type checks.
   */
  public function testAggregationTypes(): void {
    $adjusted = new AggregationType((object) [
      'Id' => 1,
      'Name' => 'Adjusted',
    ]);
    $full = new AggregationType((object) [
      'Id' => 2,
      'Name' => 'Full',
    ]);

    $this->assertTrue($adjusted->isAdjusted());
    $this->assertFalse($adjusted->isFull());
    $this->assertFalse($full->isAdjusted());
    $this->assertTrue($full->isFull());
  }

}
