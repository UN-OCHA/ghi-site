<?php

namespace Drupal\Tests\hpc_api\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\hpc_api\Traits\ObjectFilterTrait;
use Drupal\hpc_api\ApiObjects\ApiObjectInterface;

/**
 * @covers Drupal\hpc_api\Traits\ObjectFilterTrait
 */
class ObjectFilterTraitTest extends UnitTestCase {

  use ObjectFilterTrait;

  /**
   * Data provider for testFilterObjects.
   */
  public function filterObjectsDataProvider() {
    return [
      'filter by scalar value' => [
        [
          ['name' => 'John', 'age' => 30],
          ['name' => 'Jane', 'age' => 25],
        ],
        ['name' => 'John'],
        1,
      ],
      'filter by array value' => [
        [
          ['name' => 'John', 'type' => 'A'],
          ['name' => 'Jane', 'type' => 'B'],
          ['name' => 'Bob', 'type' => 'A'],
        ],
        ['type' => ['A']],
        2,
      ],
      'filter by string case insensitive' => [
        [
          ['name' => 'John', 'type' => 'A'],
          ['name' => 'Jane', 'type' => 'B'],
        ],
        ['name' => 'john'],
        1,
      ],
    ];
  }

  /**
   * Test filterObjects with mock objects.
   *
   * @dataProvider filterObjectsDataProvider
   * @group ObjectFilterTrait
   */
  public function testFilterObjects($objects_data, $filter, $expected_count) {
    $objects = array_map(function ($data) {
      return new TestApiObject($data);
    }, $objects_data);

    $this->filterObjects($objects, $filter);
    $this->assertCount($expected_count, $objects);
  }

  /**
   * Test filterObjects throws exception for non-scalar/array values.
   *
   * @group ObjectFilterTrait
   */
  public function testFilterObjectsThrowsExceptionForInvalidFilter() {
    $objects = [new TestApiObject(['name' => 'John'])];

    $this->expectException(\InvalidArgumentException::class);
    $this->filterObjects($objects, ['name' => new \stdClass()]);
  }

  /**
   * Test filterObjects with empty filter.
   *
   * @group ObjectFilterTrait
   */
  public function testFilterObjectsWithEmptyFilter() {
    $objects = [
      new TestApiObject(['name' => 'John']),
      new TestApiObject(['name' => 'Jane']),
    ];

    $this->filterObjects($objects, []);
    $this->assertCount(2, $objects);
  }

}

/**
 * Test helper class implementing ApiObjectInterface.
 */
class TestApiObject implements ApiObjectInterface {

  /**
   * The raw data.
   *
   * @var object
   */
  private $data;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $data) {
    $this->data = (object) $data;
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return 1;
  }

  /**
   * {@inheritdoc}
   */
  public function getRawData() {
    return $this->data;
  }

  /**
   * {@inheritdoc}
   */
  public static function getGraphQlItems() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function getObjectLookupProperties(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function getObjectStorageKey(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    return [];
  }

}
