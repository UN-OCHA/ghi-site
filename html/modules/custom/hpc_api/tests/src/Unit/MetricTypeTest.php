<?php

namespace Drupal\Tests\hpc_api\Unit;

use Drupal\hpc_api\ApiObjects\Types\MetricType;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for metric type objects.
 */
#[CoversClass(MetricType::class)]
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
