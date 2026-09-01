<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ghi_blocks\Helpers\AttachmentMatcher;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;

/**
 * @covers Drupal\ghi_blocks\Helpers\AttachmentMatcher
 */
class AttachmentMatcherTest extends UnitTestCase {

  /**
   * Data provider for testMatchDataPointOnAttachmentPrototypes.
   */
  public function matchDataPointOnAttachmentPrototypesDataProvider() {
    return [
      'same type returns original index' => [
        ['type_a', 'type_b', 'type_c'],
        ['type_a', 'type_b', 'type_c'],
        1,
        1,
      ],
      'different type finds new index' => [
        ['type_a', 'type_b', 'type_c'],
        ['type_a', 'type_c', 'type_b'],
        1,
        2,
      ],
      'type not found returns original index' => [
        ['type_a', 'type_b', 'type_c'],
        ['type_x', 'type_y', 'type_z'],
        1,
        1,
      ],
      'index out of bounds returns original index' => [
        ['type_a', 'type_b'],
        ['type_a', 'type_b', 'type_c'],
        5,
        5,
      ],
      'first type match' => [
        ['type_a', 'type_b'],
        ['type_b', 'type_a'],
        0,
        1,
      ],
      'last type match' => [
        ['type_a', 'type_b'],
        ['type_b', 'type_a'],
        1,
        0,
      ],
    ];
  }

  /**
   * Test matchDataPointOnAttachmentPrototypes.
   *
   * @dataProvider matchDataPointOnAttachmentPrototypesDataProvider
   * @group AttachmentMatcher
   */
  public function testMatchDataPointOnAttachmentPrototypes(array $original_fields, array $new_fields, int $data_point_index, int $expected) {
    $prototype_1 = $this->createMockAttachmentPrototype($original_fields);
    $prototype_2 = $this->createMockAttachmentPrototype($new_fields);

    $result = AttachmentMatcher::matchDataPointOnAttachmentPrototypes($data_point_index, $prototype_1, $prototype_2);
    $this->assertSame($expected, $result);
  }

  /**
   * Create a mock AttachmentPrototype with field types.
   */
  private function createMockAttachmentPrototype(array $field_types) {
    $prototype = $this->createMock(AttachmentPrototype::class);
    $prototype->method('getFieldTypes')->willReturn($field_types);
    $prototype->method('getMetricTypeByOriginalIndex')->willReturnCallback(function ($index) use ($field_types) {
      return $field_types[$index] ?? NULL;
    });
    $prototype->method('getOriginalIndexByMetricType')->willReturnCallback(function ($metric_type) use ($field_types) {
      $index = array_search($metric_type, $field_types);
      return $index === FALSE ? NULL : $index;
    });
    return $prototype;
  }

}
