<?php

namespace Drupal\Tests\hpc_api\Unit;

use Drupal\hpc_api\Helpers\ArrayHelper;
use Drupal\Tests\UnitTestCase;
use Drupal\hpc_api\Query\EndpointQuery;

/**
 * @covers Drupal\hpc_api\Helpers\ArrayHelper
 */
class ArrayHelperTest extends UnitTestCase {

  /**
   * Data provider for filterArrayByProperties.
   */
  public function filterArrayByPropertiesDataProvider() {
    $array = [];
    $outputWithPopulationFilter = [];
    $outputWithContinentFilter = [];
    // Object 1.
    $object1 = (object) [
      'id' => 1,
      'name' => 'India',
      'population' => '1.3 bn',
    ];
    array_push($array, $object1);
    // Object 2.
    $object2 = (object) [
      'id' => 2,
      'name' => 'Germany',
      'continent' => 'Europe',
    ];
    array_push($array, $object2);
    // Object 3.
    $object3 = (object) [
      'id' => 3,
      'name' => 'France',
      'population' => '40 mn',
    ];
    array_push($array, $object3);

    // Expected output when filter of population is applied.
    $outputWithPopulationFilter[0] = $object1;
    $outputWithPopulationFilter[2] = $object3;
    // Expected output when filter of continent is applied.
    $outputWithContinentFilter[1] = $object2;

    return [
      [$array, ['population'], $outputWithPopulationFilter],
      [$array, ['continent'], $outputWithContinentFilter],
    ];
  }

  /**
   * Test filter array by property.
   *
   * @group ArrayHelper
   * @dataProvider filterArrayByPropertiesDataProvider
   */
  public function testFilterArrayByProperties($array, $properties, $result) {
    $this->assertEquals($result, ArrayHelper::filterArrayByProperties($array, $properties));
  }

  /**
   * Data provider for filterArrayBySearchArray.
   */
  public function filterArrayBySearchArrayDataProvider() {
    // Prepare a mock array.
    $array = [
      0 => [
        'field' => 'flow_property_simple',
        'property' => 'id',
      ],
      1 => [
        'field' => 'flow_property_directional',
        'object_type' => 'organizations',
        'direction' => 'source',
      ],
      2 => [
        'field' => 'flow_property_simple',
        'property' => 'description',
      ],
      3 => [
        'field' => 'flow_property_simple',
        'property' => 'amountUSD',
      ],
      4 => [
        'field' => 'flow_property_directional',
        'object_type' => 'plans',
        'direction' => 'destination',
      ],
      5 => [
        'field' => 'flow_property_directional',
        'object_type' => 'locations',
        'direction' => 'destination',
      ],
    ];

    return [
      [$array, ['field' => 'flow_property_simple', 'property' => 'amountUSD'], [3]],
      [$array, ['direction' => 'destination'], [4, 5]],
    ];
  }

  /**
   * Test filter array by search array.
   *
   * @group ArrayHelper
   * @dataProvider filterArrayBySearchArrayDataProvider
   */
  public function testFilterArrayBySearchArray($data, $search_array, $result_order) {
    $result = ArrayHelper::filterArrayBySearchArray($data, $search_array);
    $expected = array_combine($result_order, array_map(function ($key) use ($data) {
      return $data[$key];
    }, $result_order));
    $this->assertSame($expected, $result);
  }

  /**
   * Data provider for sortArray.
   */
  public function sortArrayDataProvider() {
    $array = [
      'apple' => [
        'total' => 200,
        'name' => 'Apple',
      ],
      'strawberry' => [
        'total' => 500,
        'name' => 'Strawberry',
      ],
      'orange' => [
        'total' => 250,
        'name' => 'Orange',
      ],
    ];

    return [
      [$array, 'total', EndpointQuery::SORT_ASC, SORT_NUMERIC, ['apple', 'orange', 'strawberry']],
      [$array, 'total', EndpointQuery::SORT_DESC, SORT_NUMERIC, ['strawberry', 'orange', 'apple']],
      [$array, 'name', EndpointQuery::SORT_ASC, SORT_STRING, ['apple', 'orange', 'strawberry']],
      [$array, 'name', EndpointQuery::SORT_DESC, SORT_STRING, ['strawberry', 'orange', 'apple']],
    ];
  }

  /**
   * Test sort array.
   *
   * @group ArrayHelper
   * @dataProvider sortArrayDataProvider
   */
  public function testSortArray($data, $order, $sort, $sort_type, $result_order) {
    ArrayHelper::sortArray($data, $order, $sort, $sort_type);
    $expected = array_combine($result_order, array_map(function ($key) use ($data) {
      return $data[$key];
    }, $result_order));
    $this->assertSame($expected, $data);
  }

  /**
   * Data provider for sortArrayByProgress.
   */
  public function sortArrayByProgressDataProvider() {
    $array = [
      0 => ['total' => 200, 'name' => 'Apple'],
      1 => ['total' => 500, 'name' => 'Strawberry'],
      2 => ['total' => 250, 'name' => 'Orange'],
    ];

    return [
      [$array, 'total', EndpointQuery::SORT_ASC, 100, [0, 2, 1]],
      [$array, 'total', EndpointQuery::SORT_DESC, 100, [1, 2, 0]],
    ];
  }

  /**
   * Test sort array by progress.
   *
   * @group ArrayHelper
   * @dataProvider sortArrayByProgressDataProvider
   */
  public function testSortArrayByProgress($data, $order, $sort, $total, $result_order) {
    ArrayHelper::sortArrayByProgress($data, $order, $sort, $total);
    $expected = array_combine($result_order, array_map(function ($key) use ($data) {
      return $data[$key];
    }, $result_order));
    $this->assertSame($expected, $data);
  }

  /**
   * Data provider for sortArrayByCompositeArrayKey.
   */
  public function sortArrayByCompositeArrayKeyDataProvider() {
    $array = [
      0 => [
        'name' => 'WASH Emergency Rapid Response to Conflict Affected Populations',
        'organizations' => ['2178:Norwegian Refugee Council'],
        'code' => 'NGA-18/WS/122391/5834',
      ],
      1 => [
        'name' => 'Emergency shelter and Camp Management to support displaced population',
        'organizations' => ['3244:INTERSOS Humanitarian Aid Organization'],
        'code' => 'NGA-18/CCCM/120179/5660',
      ],
      2 => [
        'name' => 'Provision of Humanitarian Air Services in Nigeria',
        'organizations' => ['3049:World Food Programme'],
        'code' => 'NGA-18/LOG/122420/561',
      ],
      3 => [
        'name' => 'Provision of safe and equitable access to inclusive education',
        'organizations' => ['2915:United Nations Children Fund'],
        'code' => 'NGA-18/E/122686/124',
      ],
    ];

    return [
      [$array, 'organizations', EndpointQuery::SORT_ASC, [1, 0, 3, 2]],
      [$array, 'organizations', EndpointQuery::SORT_DESC, [2, 3, 0, 1]],
    ];
  }

  /**
   * Test sort array by composite array key.
   *
   * @group ArrayHelper
   * @dataProvider sortArrayByCompositeArrayKeyDataProvider
   */
  public function testSortArrayByCompositeArrayKey($data, $order, $sort, $result_order) {
    ArrayHelper::sortArrayByCompositeArrayKey($data, $order, $sort);
    $expected = array_combine($result_order, array_map(function ($key) use ($data) {
      return $data[$key];
    }, $result_order));
    $this->assertSame($expected, $data);
  }

  /**
   * Data provider for sortArrayByObjectListProperty.
   */
  public function sortArrayByObjectListPropertyDataProvider() {
    $array = [
      0 => [
        'name' => 'WASH Emergency Rapid Response to Conflict Affected Populations',
        'fields' => [
          (object) ['name' => 'Gender Marker', 'value' => 'Mark females'],
          (object) ['name' => 'Project Priority', 'value' => 'High'],
        ],
      ],
      1 => [
        'name' => 'Emergency shelter and Camp Management to support displaced population',
        'fields' => [
          (object) ['name' => 'Gender Marker', 'value' => 'Highlight Males'],
          (object) ['name' => 'Project Priority', 'value' => 'Low'],
        ],
      ],
      2 => [
        'name' => 'Provision of Humanitarian Air Services in Nigeria',
        'fields' => [
          (object) ['name' => 'Gender Marker', 'value' => 'Not specified'],
          (object) ['name' => 'Project Priority', 'value' => 'High'],
        ],
      ],
    ];

    return [
      [$array, 'Gender Marker', EndpointQuery::SORT_ASC, [1, 0, 2]],
      [$array, 'Gender Marker', EndpointQuery::SORT_DESC, [2, 0, 1]],
    ];
  }

  /**
   * Test sort array by object list property.
   *
   * @group ArrayHelper
   * @dataProvider sortArrayByObjectListPropertyDataProvider
   */
  public function testSortArrayByObjectListProperty($data, $order, $sort, $result_order) {
    ArrayHelper::sortArrayByObjectListProperty($data, $order, $sort);
    $expected = array_combine($result_order, array_map(function ($key) use ($data) {
      return $data[$key];
    }, $result_order));
    $this->assertSame($expected, $data);
  }

  /**
   * Data provider for findFirstItemByProperties.
   */
  public function findFirstItemByPropertiesDataProvider() {
    $array = [
      0 => ['name' => 'Bill', 'surname' => 'Gates', 'country' => 'USA'],
      1 => ['name' => 'Abdul', 'surname' => 'Kalam', 'country' => 'India'],
      2 => ['name' => 'Abdul', 'surname' => 'Hafiz', 'country' => 'Pakistan'],
    ];
    return [
      [$array, ['name' => 'Bill'], $array[0]],
      [$array, ['name' => 'Abdul'], $array[1]],
      [$array, ['name' => 'Abdul', 'country' => 'Pakistan'], $array[2]],
    ];
  }

  /**
   * Test find first by property.
   *
   * @group ArrayHelper
   * @dataProvider findFirstItemByPropertiesDataProvider
   */
  public function testFindFirstItemByProperties($data, $parameters, $result) {
    $this->assertEquals($result, ArrayHelper::findFirstItemByProperties($data, $parameters));
  }

  /**
   * Data provider for extendAssociativeArray.
   */
  public function extendAssociativeArrayDataProvider() {
    $array = [
      'name' => 'Bill',
      'surname' => 'Gates',
      'country' => 'USA',
    ];

    // Result for 1st data set.
    $result_1 = [
      'name' => 'Bill',
      'surname' => 'Gates',
      'country' => 'USA',
      'company' => 'Microsoft',
    ];

    // Result for 2nd data set.
    $result_2 = [
      'name' => 'Bill',
      'surname' => 'Gates',
      'job' => 'CEO',
      'country' => 'USA',
    ];

    return [
      [$array, 'company', 'Microsoft', NULL, $result_1],
      [$array, 'job', 'CEO', 2, $result_2],
    ];
  }

  /**
   * Test extending an associative array.
   *
   * @group ArrayHelper
   * @dataProvider extendAssociativeArrayDataProvider
   */
  public function testExtendAssociativeArray($data, $key, $value, $pos, $result) {
    ArrayHelper::extendAssociativeArray($data, $key, $value, $pos);
    $this->assertEquals($result, $data);
  }

  /**
   * Data provider for sumObjectsByProperty.
   */
  public function sumObjectsByPropertyDataProvider() {
    $array = [
      (object) ['item' => 'mobile', 'cost' => 1500],
      (object) ['item' => 'tshirt', 'cost' => 200],
      (object) ['item' => 'laptop', 'cost' => 2000],
    ];

    return [
      [$array, 'cost', 3700],
      [[], 'cost', 0],
    ];
  }

  /**
   * Test sum objects by property.
   *
   * @group ArrayHelper
   * @dataProvider sumObjectsByPropertyDataProvider
   */
  public function testSumObjectsByProperty($data, $property, $result) {
    $this->assertEquals($result, ArrayHelper::sumObjectsByProperty($data, $property));
  }

  /**
   * Data provider for sortObjectsByProperty.
   */
  public function sortObjectsByPropertyDataProvider() {
    $array = [
      0 => (object) ['item' => 'mobile', 'cost' => 1500],
      1 => (object) ['item' => 'tshirt', 'cost' => 200],
      2 => (object) ['item' => 'laptop', 'cost' => 2000],
    ];

    return [
      [$array, 'cost', EndpointQuery::SORT_ASC, SORT_NUMERIC, [1, 0, 2]],
      [$array, 'cost', EndpointQuery::SORT_DESC, SORT_NUMERIC, [2, 0, 1]],
      [$array, 'item', EndpointQuery::SORT_ASC, SORT_STRING, [2, 0, 1]],
      [$array, 'item', EndpointQuery::SORT_DESC, SORT_STRING, [1, 0, 2]],
    ];
  }

  /**
   * Test sort objects by property.
   *
   * @group ArrayHelper
   * @dataProvider sortObjectsByPropertyDataProvider
   */
  public function testSortObjectsByProperty($data, $property, $sort, $sort_type, $result_order) {
    ArrayHelper::sortObjectsByProperty($data, $property, $sort, $sort_type);
    $expected = array_combine($result_order, array_map(function ($key) use ($data) {
      return $data[$key];
    }, $result_order));
    $this->assertSame($expected, $data);
  }

  /**
   * Data provider for sortObjectsByMethod.
   */
  public function sortObjectsByMethodDataProvider() {
    $class = function ($item, $cost) {
      // @codingStandardsIgnoreStart
      return new class ($item, $cost) {
        private $item;
        private $cost;
        public function __construct($item, $cost) {
          $this->item = $item;
          $this->cost = $cost;
        }
        public function getCost() { return $this->cost; }
        public function getItem() { return $this->item; }
      };
      // @codingStandardsIgnoreEnd
    };

    $array = [
      0 => $class('mobile', 1500),
      1 => $class('tshirt', 200),
      2 => $class('laptop', 2000),
    ];

    return [
      [$array, 'getCost', EndpointQuery::SORT_ASC, SORT_NUMERIC, [1, 0, 2]],
      [$array, 'getCost', EndpointQuery::SORT_DESC, SORT_NUMERIC, [2, 0, 1]],
      [$array, 'getItem', EndpointQuery::SORT_ASC, SORT_STRING, [2, 0, 1]],
      [$array, 'getItem', EndpointQuery::SORT_DESC, SORT_STRING, [1, 0, 2]],
    ];
  }

  /**
   * Test sort objects by property.
   *
   * @group ArrayHelper
   * @dataProvider sortObjectsByMethodDataProvider
   */
  public function testSortObjectsByMethod($data, $method, $sort, $sort_type, $result_order) {
    ArrayHelper::sortObjectsByMethod($data, $method, $sort, $sort_type);
    $expected = array_combine($result_order, array_map(function ($key) use ($data) {
      return $data[$key];
    }, $result_order));
    $this->assertSame($expected, $data);
  }

}
