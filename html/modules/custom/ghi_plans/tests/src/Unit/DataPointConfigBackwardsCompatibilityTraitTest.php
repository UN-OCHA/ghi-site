<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ghi_plans\Traits\DataPointConfigBackwardsCompatibilityTrait;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;

/**
 * @covers Drupal\ghi_plans\Traits\DataPointConfigBackwardsCompatibilityTrait
 */
class DataPointConfigBackwardsCompatibilityTraitTest extends UnitTestCase {

  use DataPointConfigBackwardsCompatibilityTrait;

  /**
   * Test getMetricTypeByIndex returns correct metric type.
   *
   * @group DataPointConfigBackwardsCompatibilityTrait
   */
  public function testGetMetricTypeByIndex() {
    $prototype = $this->mockPrototype(['type_a', 'type_b', 'type_c']);

    $result = $this->getMetricTypeByIndex(1, $prototype);
    $this->assertSame('type_b', $result);
  }

  /**
   * Test getMetricTypeByIndex delegates to the attachment prototype.
   *
   * @group DataPointConfigBackwardsCompatibilityTrait
   */
  public function testGetMetricTypeByIndexUsesPrototypeResolver() {
    $prototype = $this->createMock(AttachmentPrototype::class);
    $prototype->expects($this->once())
      ->method('resolveMetricType')
      ->with(8)
      ->willReturn('latest_reach');

    $result = $this->getMetricTypeByIndex(8, $prototype);
    $this->assertSame('latest_reach', $result);
  }

  /**
   * Test getMetricTypeByIndex returns null for out of bounds index.
   *
   * @group DataPointConfigBackwardsCompatibilityTrait
   */
  public function testGetMetricTypeByIndexOutOfBounds() {
    $prototype = $this->mockPrototype(['type_a', 'type_b']);

    $result = $this->getMetricTypeByIndex(10, $prototype);
    $this->assertNull($result);
  }

  /**
   * Test updateDataPointConfiguration adds metric type.
   *
   * @group DataPointConfigBackwardsCompatibilityTrait
   */
  public function testUpdateDataPointConfiguration() {
    $prototype = $this->mockPrototype(['type_a', 'type_b']);

    $conf = [
      'data_points' => [
        ['index' => 0],
        ['index' => '1'],
      ],
    ];

    $this->updateDataPointConfiguration($conf, $prototype);

    $this->assertSame('type_a', $conf['data_points'][0]['metric_type']);
    $this->assertSame('type_b', $conf['data_points'][1]['metric_type']);
  }

  /**
   * Test updateDataPointConfiguration skips existing metric types.
   *
   * @group DataPointConfigBackwardsCompatibilityTrait
   */
  public function testUpdateDataPointConfigurationSkipsExisting() {
    $prototype = $this->mockPrototype(['type_a', 'type_b']);

    $conf = [
      'data_points' => [
        ['index' => 0, 'metric_type' => 'existing'],
        ['index' => 1],
      ],
    ];

    $this->updateDataPointConfiguration($conf, $prototype);

    $this->assertSame('existing', $conf['data_points'][0]['metric_type']);
    $this->assertSame('type_b', $conf['data_points'][1]['metric_type']);
  }

  /**
   * Tests recovery of a metric type stored under the legacy index key.
   *
   * @group DataPointConfigBackwardsCompatibilityTrait
   */
  public function testUpdateDataPointConfigurationRecoversMisplacedMetricType() {
    $prototype = $this->mockPrototype(['type_a', 'type_b']);
    $conf = [
      'data_points' => [
        ['index' => 'type_a'],
      ],
    ];

    $this->updateDataPointConfiguration($conf, $prototype);

    $this->assertSame('type_a', $conf['data_points'][0]['metric_type']);
  }

  /**
   * Create a mock AttachmentPrototype.
   */
  private function mockPrototype(array $field_types) {
    $prototype = $this->createMock(AttachmentPrototype::class);
    $prototype->method('getFieldTypes')->willReturn($field_types);
    $prototype->method('resolveMetricType')->willReturnCallback(function ($data_point) use ($field_types) {
      if (is_int($data_point) || (is_string($data_point) && ctype_digit($data_point))) {
        return $field_types[(int) $data_point] ?? NULL;
      }
      return is_string($data_point) && in_array($data_point, $field_types, TRUE) ? $data_point : NULL;
    });
    return $prototype;
  }

}
