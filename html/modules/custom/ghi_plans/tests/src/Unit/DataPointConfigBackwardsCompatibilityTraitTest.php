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
    $prototype = $this->createMockPrototype(['type_a', 'type_b', 'type_c']);

    $result = $this->getMetricTypeByIndex(1, $prototype);
    $this->assertSame('type_b', $result);
  }

  /**
   * Test getMetricTypeByIndex prefers original legacy field positions.
   *
   * @group DataPointConfigBackwardsCompatibilityTrait
   */
  public function testGetMetricTypeByIndexUsesOriginalIndexDefinitions() {
    $prototype = $this->createMockPrototype(['type_a', 'type_b']);
    $prototype->method('getMetricTypeByOriginalIndex')
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
    $prototype = $this->createMockPrototype(['type_a', 'type_b']);

    $result = $this->getMetricTypeByIndex(10, $prototype);
    $this->assertNull($result);
  }

  /**
   * Test updateDataPointConfiguration adds metric type.
   *
   * @group DataPointConfigBackwardsCompatibilityTrait
   */
  public function testUpdateDataPointConfiguration() {
    $prototype = $this->createMockPrototype(['type_a', 'type_b']);

    $conf = [
      'data_points' => [
        ['index' => 0],
        ['index' => 1],
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
    $prototype = $this->createMockPrototype(['type_a', 'type_b']);

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
   * Create a mock AttachmentPrototype.
   */
  private function createMockPrototype(array $field_types) {
    $prototype = $this->createMock(AttachmentPrototype::class);
    $prototype->method('getFieldTypes')->willReturn($field_types);
    return $prototype;
  }

}
