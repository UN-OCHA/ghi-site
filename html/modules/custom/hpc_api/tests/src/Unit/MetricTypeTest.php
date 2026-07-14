<?php

namespace Drupal\Tests\hpc_api\Unit;

use Drupal\hpc_api\ApiObjects\Types\MetricType;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for metric type objects.
 *
 * @covers \Drupal\hpc_api\ApiObjects\Types\MetricType
 */
class MetricTypeTest extends UnitTestCase {

  /**
   * Test exact and normalized matching against label lookups.
   */
  public function testMatchesLabelLookupCaseSensitivity(): void {
    $metric_type = new MetricType((object) [
      'Id' => 22,
      'Name' => 'Measure Cumulative',
      'HPCType' => 'cumulativeMeasure',
      'LabelLookup' => 'Measure (cumulative)|Cumulative measure',
    ]);

    $this->assertTrue($metric_type->matches('Cumulative measure', TRUE));
    $this->assertFalse($metric_type->matches('cumulative measure', TRUE));
    $this->assertTrue($metric_type->matches('cumulative measure'));
  }

}
