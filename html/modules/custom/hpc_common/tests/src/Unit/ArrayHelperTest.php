<?php

namespace Drupal\Tests\hpc_common\Unit;

require_once 'html/modules/custom/hpc_api/hpc_api.module';

use Drupal\Tests\UnitTestCase;

use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_common\Helpers\ArrayHelper;

/**
 * @covers Drupal\hpc_common\Helpers\ArrayHelper
 */
class ArrayHelperTest extends UnitTestCase {

  /**
   * The array helper class.
   *
   * @var \Drupal\hpc_common\Helpers\ArrayHelper
   */
  protected $arrayHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->arrayHelper = new ArrayHelper();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    parent::tearDown();
    unset($this->arrayHelper);
  }

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
    $this->assertArrayEquals($result, $this->arrayHelper->filterArrayByProperties($array, $properties));
  }

  /**
   * Data provider for filterArrayBySearchArray.
   */
  public function filterArrayBySearchArrayDataProvider() {
    // Prepare a mock array.
    $array = [
      [
        'field' => 'flow_property_simple',
        'property' => 'id',
      ],
      [
        'field' => 'flow_property_directional',
        'object_type' => 'organizations',
        'direction' => 'source',
      ],
      [
        'field' => 'flow_property_simple',
        'property' => 'description',
      ],
      [
        'field' => 'flow_property_simple',
        'property' => 'amountUSD',
      ],
      [
        'field' => 'flow_property_directional',
        'object_type' => 'plans',
        'direction' => 'destination',
      ],
      [
        'field' => 'flow_property_directional',
        'object_type' => 'locations',
        'direction' => 'destination',
      ],
    ];

    // Prepare mock search array.
    $search_array_1 = [
      'field' => 'flow_property_simple',
      'property' => 'amountUSD',
    ];

    // Prepare mock result for the above search.
    $result_1 = [
      3 => [
        'field' => 'flow_property_simple',
        'property' => 'amountUSD',
      ],
    ];

    // Prepare mock search array.
    $search_array_2 = ['direction' => 'destination'];

    // Prepare mock result for the above search.
    $result_2 = [
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
      [$array, $search_array_1, $result_1],
      [$array, $search_array_2, $result_2],
    ];
  }

  /**
   * Test filter array by search array.
   *
   * @group ArrayHelper
   * @dataProvider filterArrayBySearchArrayDataProvider
   */
  public function testFilterArrayBySearchArray($array, $search_array, $result) {
    $this->assertArrayEquals($result, $this->arrayHelper->filterArrayBySearchArray($array, $search_array));
  }

  /**
   * Data provider for sortArray.
   */
  public function sortArrayDataProvider() {
    $array = [
      ['total' => 200, 'name' => 'Apple'],
      ['total' => 500, 'name' => 'Strawberry'],
      ['total' => 250, 'name' => 'Orange'],
    ];

    // Result for 1st set of options.
    $result_1 = [
      0 => ['total' => 200, 'name' => 'Apple'],
      2 => ['total' => 250, 'name' => 'Orange'],
      1 => ['total' => 500, 'name' => 'Strawberry'],
    ];

    // Result for 2nd set of options.
    $result_2 = [
      1 => ['total' => 500, 'name' => 'Strawberry'],
      2 => ['total' => 250, 'name' => 'Orange'],
      0 => ['total' => 200, 'name' => 'Apple'],
    ];

    // Result for 3rd set of options.
    $result_3 = [
      0 => ['total' => 200, 'name' => 'Apple'],
      1 => ['total' => 250, 'name' => 'Orange'],
      2 => ['total' => 500, 'name' => 'Strawberry'],
    ];

    // Result for 2nd set of options.
    $result_4 = [
      0 => ['total' => 500, 'name' => 'Strawberry'],
      1 => ['total' => 250, 'name' => 'Orange'],
      2 => ['total' => 200, 'name' => 'Apple'],
    ];

    return [
      [$array, 'total', EndpointQuery::SORT_ASC, SORT_NUMERIC, $result_1],
      [$array, 'total', EndpointQuery::SORT_DESC, SORT_NUMERIC, $result_2],
      [$array, 'name', EndpointQuery::SORT_ASC, SORT_STRING, $result_3],
      [$array, 'name', EndpointQuery::SORT_DESC, SORT_STRING, $result_4],
    ];
  }

  /**
   * Test sort array.
   *
   * @group ArrayHelper
   * @dataProvider sortArrayDataProvider
   */
  public function testSortArray($data, $order, $sort, $sort_type, $result) {
    $this->arrayHelper->sortArray($data, $order, $sort, $sort_type);
    $this->assertArrayEquals($result, $data);
  }

  /**
   * Data provider for sortArrayByProgress.
   */
  public function sortArrayByProgressDataProvider() {
    $array = [
      ['total' => 200, 'name' => 'Apple'],
      ['total' => 500, 'name' => 'Strawberry'],
      ['total' => 250, 'name' => 'Orange'],
    ];

    // Result for 1st set of options.
    $result_1 = [
      0 => ['total' => 200, 'name' => 'Apple'],
      2 => ['total' => 250, 'name' => 'Orange'],
      1 => ['total' => 500, 'name' => 'Strawberry'],
    ];

    // Result for 2nd set of options.
    $result_2 = [
      1 => ['total' => 500, 'name' => 'Strawberry'],
      2 => ['total' => 250, 'name' => 'Orange'],
      0 => ['total' => 200, 'name' => 'Apple'],
    ];

    return [
      [$array, 'total', EndpointQuery::SORT_ASC, 100, $result_1],
      [$array, 'total', EndpointQuery::SORT_DESC, 100, $result_2],
    ];
  }

  /**
   * Test sort array by progress.
   *
   * @group ArrayHelper
   * @dataProvider sortArrayByProgressDataProvider
   */
  public function testSortArrayByProgress($data, $order, $sort, $total, $result) {
    $this->arrayHelper->sortArrayByProgress($data, $order, $sort, $total);
    $this->assertArrayEquals($result, $data);
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

    // Result for 1st data set.
    $result_1 = [
      1 => [
        'name' => 'Emergency shelter and Camp Management to support displaced population',
        'organizations' => ['3244:INTERSOS Humanitarian Aid Organization'],
        'code' => 'NGA-18/CCCM/120179/5660',
      ],
      0 => [
        'name' => 'WASH Emergency Rapid Response to Conflict Affected Populations',
        'organizations' => ['2178:Norwegian Refugee Council'],
        'code' => 'NGA-18/WS/122391/5834',
      ],
      3 => [
        'name' => 'Provision of safe and equitable access to inclusive education',
        'organizations' => ['2915:United Nations Children Fund'],
        'code' => 'NGA-18/E/122686/124',
      ],
      2 => [
        'name' => 'Provision of Humanitarian Air Services in Nigeria',
        'organizations' => ['3049:World Food Programme'],
        'code' => 'NGA-18/LOG/122420/561',
      ],
    ];

    // Result for 2nd data set.
    $result_2 = [
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
    ];

    return [
      [$array, 'organizations', EndpointQuery::SORT_ASC, $result_1],
      [$array, 'organizations', EndpointQuery::SORT_DESC, $result_2],
    ];
  }

  /**
   * Test sort array by composite array key.
   *
   * @group ArrayHelper
   * @dataProvider sortArrayByCompositeArrayKeyDataProvider
   */
  public function testSortArrayByCompositeArrayKey($data, $order, $sort, $result) {
    $this->arrayHelper->sortArrayByCompositeArrayKey($data, $order, $sort);
    $this->assertArrayEquals($result, $data);
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

    // Result for 1st data set.
    $result_1 = [
      0 => [
        'name' => 'Emergency shelter and Camp Management to support displaced population',
        'fields' => [
          (object) ['name' => 'Gender Marker', 'value' => 'Highlight Males'],
          (object) ['name' => 'Project Priority', 'value' => 'Low'],
        ],
      ],
      1 => [
        'name' => 'WASH Emergency Rapid Response to Conflict Affected Populations',
        'fields' => [
          (object) ['name' => 'Gender Marker', 'value' => 'Mark females'],
          (object) ['name' => 'Project Priority', 'value' => 'High'],
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

    // Result for 2nd data set.
    $result_2 = [
      0 => [
        'name' => 'Provision of Humanitarian Air Services in Nigeria',
        'fields' => [
          (object) ['name' => 'Gender Marker', 'value' => 'Not specified'],
          (object) ['name' => 'Project Priority', 'value' => 'High'],
        ],
      ],
      1 => [
        'name' => 'WASH Emergency Rapid Response to Conflict Affected Populations',
        'fields' => [
          (object) ['name' => 'Gender Marker', 'value' => 'Mark females'],
          (object) ['name' => 'Project Priority', 'value' => 'High'],
        ],
      ],
      2 => [
        'name' => 'Emergency shelter and Camp Management to support displaced population',
        'fields' => [
          (object) ['name' => 'Gender Marker', 'value' => 'Highlight Males'],
          (object) ['name' => 'Project Priority', 'value' => 'Low'],
        ],
      ],
    ];

    return [
      [$array, 'Gender Marker', EndpointQuery::SORT_ASC, $result_1],
      [$array, 'Gender Marker', EndpointQuery::SORT_DESC, $result_2],
    ];
  }

  /**
   * Test sort array by object list property.
   *
   * @group ArrayHelper
   * @dataProvider sortArrayByObjectListPropertyDataProvider
   */
  public function testSortArrayByObjectListProperty($data, $order, $sort, $result) {
    $this->arrayHelper->sortArrayByObjectListProperty($data, $order, $sort);
    $this->assertArrayEquals($result, $data);
  }

  /**
   * Data provider for findFirstItemByProperties.
   */
  public function findFirstItemByPropertiesDataProvider() {
    $array = [
      [
        'name' => 'Bill',
        'surname' => 'Gates',
        'country' => 'USA',
      ],
      [
        'name' => 'Abdul',
        'surname' => 'Kalam',
        'country' => 'India',
      ],
      [
        'name' => 'Abdul',
        'surname' => 'Hafiz',
        'country' => 'Pakistan',
      ],
    ];

    // Result of 1st data set.
    $result_1 = [
      'name' => 'Bill',
      'surname' => 'Gates',
      'country' => 'USA',
    ];

    // Result of 2nd data set.
    $result_2 = [
      'name' => 'Abdul',
      'surname' => 'Kalam',
      'country' => 'India',
    ];

    // Result of 3rd data set.
    $result_3 = [
      'name' => 'Abdul',
      'surname' => 'Hafiz',
      'country' => 'Pakistan',
    ];

    return [
      [$array, ['name' => 'Bill'], $result_1],
      [$array, ['name' => 'Abdul'], $result_2],
      [$array, ['name' => 'Abdul', 'country' => 'Pakistan'], $result_3],
    ];
  }

  /**
   * Test find first by property.
   *
   * @group ArrayHelper
   * @dataProvider findFirstItemByPropertiesDataProvider
   */
  public function testFindFirstItemByProperties($data, $parameters, $result) {
    $this->assertArrayEquals($result, $this->arrayHelper->findFirstItemByProperties($data, $parameters));
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
    $this->arrayHelper->extendAssociativeArray($data, $key, $value, $pos);
    $this->assertArrayEquals($result, $data);
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
    $this->assertEquals($result, $this->arrayHelper->sumObjectsByProperty($data, $property));
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

    // Result for 1st data set.
    $result_1 = [
      1 => (object) ['item' => 'tshirt', 'cost' => 200],
      0 => (object) ['item' => 'mobile', 'cost' => 1500],
      2 => (object) ['item' => 'laptop', 'cost' => 2000],
    ];

    // Result for 2nd data set.
    $result_2 = [
      2 => (object) ['item' => 'laptop', 'cost' => 2000],
      0 => (object) ['item' => 'mobile', 'cost' => 1500],
      1 => (object) ['item' => 'tshirt', 'cost' => 200],
    ];

    // Result for 3rd data set.
    $result_3 = [
      2 => (object) ['item' => 'laptop', 'cost' => 2000],
      0 => (object) ['item' => 'mobile', 'cost' => 1500],
      1 => (object) ['item' => 'tshirt', 'cost' => 200],
    ];

    // Result for 4th data set.
    $result_4 = [
      1 => (object) ['item' => 'tshirt', 'cost' => 200],
      0 => (object) ['item' => 'mobile', 'cost' => 1500],
      2 => (object) ['item' => 'laptop', 'cost' => 2000],
    ];

    return [
      [$array, 'cost', EndpointQuery::SORT_ASC, SORT_NUMERIC, $result_1],
      [$array, 'cost', EndpointQuery::SORT_DESC, SORT_NUMERIC, $result_2],
      [$array, 'item', EndpointQuery::SORT_ASC, SORT_STRING, $result_3],
      [$array, 'item', EndpointQuery::SORT_DESC, SORT_STRING, $result_4],
    ];
  }

  /**
   * Test sort objects by property.
   *
   * @group ArrayHelper
   * @dataProvider sortObjectsByPropertyDataProvider
   */
  public function testSortObjectsByProperty($data, $property, $sort, $sort_type, $result) {
    $this->arrayHelper->sortObjectsByProperty($data, $property, $sort, $sort_type);
    $this->assertArrayEquals($result, $data);
  }

}
