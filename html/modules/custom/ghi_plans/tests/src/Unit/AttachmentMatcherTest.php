<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\Helpers\AttachmentMatcher;
use Drupal\Tests\UnitTestCase;

/**
 * @covers Drupal\ghi_plans\Helpers\AttachmentMatcher
 */
class AttachmentMatcherTest extends UnitTestCase {

  /**
   * Data provider for testMatchDataPointOnAttachmentPrototypes.
   */
  public function matchDataPointOnAttachmentPrototypesDataProvider() {
    return [
      'legacy index with changed field order' => [
        ['type_a', 'type_b', 'type_c'],
        ['type_a', 'type_c', 'type_b'],
        1,
        'type_b',
      ],
      'canonical metric type' => [
        ['type_a', 'type_b', 'type_c'],
        ['type_a', 'type_c', 'type_b'],
        'type_b',
        'type_b',
      ],
      'type not found' => [
        ['type_a', 'type_b', 'type_c'],
        ['type_x', 'type_y', 'type_z'],
        1,
        NULL,
      ],
      'index out of bounds' => [
        ['type_a', 'type_b'],
        ['type_a', 'type_b', 'type_c'],
        5,
        NULL,
      ],
    ];
  }

  /**
   * Test matchDataPointOnAttachmentPrototypes.
   *
   * @dataProvider matchDataPointOnAttachmentPrototypesDataProvider
   * @group AttachmentMatcher
   */
  public function testMatchDataPointOnAttachmentPrototypes(array $original_fields, array $new_fields, $data_point, ?string $expected) {
    $prototype_1 = $this->mockAttachmentPrototype($original_fields);
    $prototype_2 = $this->mockAttachmentPrototype($new_fields);

    $result = AttachmentMatcher::matchDataPointOnAttachmentPrototypes($data_point, $prototype_1, $prototype_2);
    $this->assertSame($expected, $result);
  }

  /**
   * Create a mock AttachmentPrototype with field types.
   */
  private function mockAttachmentPrototype(array $field_types) {
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
