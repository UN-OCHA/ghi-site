<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\hpc_common\Helpers\ArrayHelper;

/**
 * @covers Drupal\hpc_common\Helpers\ArrayHelper
 */
class ArrayHelperTest extends UnitTestCase {

  /**
   * Data provider for testSwapArray.
   */
  public function swapArrayDataProvider() {
    $array = [
      1 => 'one',
      2 => 'two',
      'one' => 'second one',
      'two' => 'second two',
    ];

    return [
      [$array, 1, 2, FALSE, [2, 1, 'one', 'two'], NULL],
      [$array, 1, 'one', FALSE, ['one', 2, 1, 'two'], NULL],
      [$array, 1, 3, FALSE, [1, 2, 'one', 'two'], FALSE],
      [$array, '1', 2, TRUE, [1, 2, 'one', 'two'], FALSE],
      [$array, 1, '2', TRUE, [1, 2, 'one', 'two'], FALSE],
    ];
  }

  /**
   * Test swap array function.
   *
   * @group ArrayHelper
   * @dataProvider swapArrayDataProvider
   */
  public function testSwapArray($data, $key1, $key2, $strict, $result_order, $return_value) {
    $this->assertEquals($return_value, ArrayHelper::swap($data, $key1, $key2, $strict));
    $expected = array_combine($result_order, array_map(function ($key) use ($data) {
      return $data[$key];
    }, $result_order));
    $this->assertEquals($expected, $data);
  }

  /**
   * Test arrayMapAssoc function.
   *
   * @group ArrayHelper
   */
  public function testArrayMapAssoc() {
    $array = [
      6 => ['six'],
      2 => ['two'],
      3 => ['three'],
      10 => ['ten'],
    ];
    $result = ArrayHelper::arrayMapAssoc(function ($item) {
      return $item[0];
    }, $array);
    $expected = [
      6 => 'six',
      2 => 'two',
      3 => 'three',
      10 => 'ten',
    ];
    $this->assertSame($expected, $result);
  }

  /**
   * Test mapObjectsToString function.
   *
   * @group ArrayHelper
   */
  public function testMapObjectsToString() {
    $class = function ($value) {
      // @codingStandardsIgnoreStart
      return new class ($value) {
        private $value;
        public function __construct($value) {
          $this->value = $value;
        }
        public function __toString() { return $this->value; }
      };
      // @codingStandardsIgnoreEnd
    };
    $array = [
      6 => [6 => 'six', 5 => $class('eleven'), 9 => ['one', 'three', $class('two')]],
      2 => [2 => 'two', 7 => 'seven', 5 => 'five'],
    ];
    $expected = [
      6 => [6 => 'six', 5 => 'eleven', 9 => ['one', 'three', 'two']],
      2 => [2 => 'two', 7 => 'seven', 5 => 'five'],
    ];
    $this->assertSame($expected, ArrayHelper::mapObjectsToString($array));
  }

  /**
   * Test sortMultiDimensionalArrayByKeys function.
   *
   * @group ArrayHelper
   */
  public function testSortMultiDimensionalArrayByKeys() {
    $array = [
      6 => [6 => 'six', 5 => 'five', 9 => ['one', 'three', 'two']],
      2 => [2 => 'two', 7 => 'seven', 5 => 'five'],
    ];
    $expected = [
      2 => [2 => 'two', 5 => 'five', 7 => 'seven'],
      6 => [5 => 'five', 6 => 'six', 9 => ['one', 'three', 'two']],
    ];
    ArrayHelper::sortMultiDimensionalArrayByKeys($array);
    $this->assertSame($expected, $array);
  }

  /**
   * Test reduceArray function.
   *
   * @group ArrayHelper
   */
  public function testReduceArray() {
    $array = [
      6 => [6 => 'six', 5 => 0, 9 => [], 10 => [1 => 'one', 2 => FALSE]],
      2 => [2 => 'two', 7 => NULL, 5 => 'five'],
    ];
    $expected = [
      6 => [6 => 'six', 10 => [1 => 'one']],
      2 => [2 => 'two', 5 => 'five'],
    ];
    ArrayHelper::reduceArray($array);
    $this->assertSame($expected, $array);
  }

}
