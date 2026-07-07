<?php

namespace Drupal\Tests\hpc_api\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\hpc_api\Query\FabricQuery;
use Drupal\Tests\hpc_api\Traits\PrivateAccessorTrait;

/**
 * @covers Drupal\hpc_api\Query\FabricQuery
 *
 * phpcs:disable Squiz.Arrays.ArrayDeclaration.KeySpecified
 */
class FabricQueryTest extends UnitTestCase {

  use PrivateAccessorTrait;

  /**
   * Data provider for testFilterValidation.
   */
  public function dataProviderFilterValidation() {
    return [
      [['Id' => []], TRUE],
      [['Id' => 1, 'plans' => ['Id' => []]], TRUE],
      [['Id' => 1], FALSE],
    ];
  }

  /**
   * Test filter validation.
   *
   * @group FabricQuery
   * @dataProvider dataProviderFilterValidation
   */
  public function testFilterValidation($filters, $expect_exception) {
    $fabric_query = new FabricQuery('test');
    if ($expect_exception) {
      $this->expectException(\InvalidArgumentException::class);
    }
    $result = $fabric_query->validateFilters($filters);
    $this->assertEquals($expect_exception ? NULL : TRUE, $result);
  }

  /**
   * Data provider for testBuildFilterString.
   */
  public function dataProviderBuildFilterString() {
    $cases = [];
    $one_to_hundred = range(1, 100);
    $cases[] = [
      [
        'Id' => 10,
        'Name' => 'Test',
      ],
      'Id: { eq: 10 } Name: { eq: "Test" }',
    ];
    $cases[] = [
      [
        'Name' => 'Test',
        'Id' => 10,
      ],
      'Name: { eq: "Test" } Id: { eq: 10 }',
    ];
    $cases[] = [
      [
        'planPeriod' => [
          'period' => [
            'CalendarYear' => 2025,
          ],
        ],
      ],
      'planPeriod: { period: { CalendarYear: { eq: 2025 } } }',
    ];
    $cases[] = [
      [
        'planPeriod' => [
          'period' => [
            'PeriodType' => 'Year',
            'CalendarYear' => 2025,
          ],
        ],
      ],
      'planPeriod: { period: { PeriodType: { eq: "Year" } CalendarYear: { eq: 2025 } } }',
    ];
    $cases[] = [
      [
        'Id' => range(1, 101),
      ],
      'or: [{ Id: { in: [' . implode(',', $one_to_hundred) . '] } }, { Id: { in: [101] } }]',
    ];
    $cases[] = [
      [
        'Id' => range(1, 101),
        'AdminLevel' => 'NOT NULL',
      ],
      'AdminLevel: { isNull: false } or: [{ Id: { in: [' . implode(',', $one_to_hundred) . '] } }, { Id: { in: [101] } }]',
    ];
    $cases[] = [
      [
        'Id' => range(1, 101),
        'ParentId' => range(101, 201),
      ],
      'and: [{or: [{ Id: { in: [' . implode(',', $one_to_hundred) . '] } }, { Id: { in: [101] } }]}, {or: [{ ParentId: { in: [' . implode(',', range(101, 200)) . '] } }, { ParentId: { in: [201] } }]}]',
    ];
    return $cases;
  }

  /**
   * Test building of the item string.
   *
   * @group FabricQuery
   * @dataProvider dataProviderBuildFilterString
   */
  public function testBuildFilterString($filters, $expected) {
    $fabric_query = new FabricQuery('test');
    $fabric_query->setFilters($filters);
    $actual = $this->callPrivateMethod($fabric_query, 'buildFilterString');
    $this->assertEquals($expected, $actual);
  }

  /**
   * Data provider for testBuildItemString.
   */
  public function dataProviderBuildItemString() {
    $cases = [];
    $cases[] = [
      [
        'Id',
        'planPeriod' => ['items' => ['period' => ['CalendarYear']]],
      ],
      'Id planPeriod { items { period { CalendarYear } } }',
    ];
    $cases[] = [
      [
        'Id',
        'planLocation' => ['items' => ['location' => ['Id', 'Name']]],
      ],
      'Id planLocation { items { location { Id Name } } }',
    ];
    $cases[] = [
      [
        'Id',
        'planOrganization' => [
          'filter' => ['RecordStatus' => 'Active'],
          'items' => ['organization' => ['Id', 'Name']],
        ],
      ],
      'Id planOrganization ( filter: { RecordStatus: { eq: "Active" } } ) { items { organization { Id Name } } }',
    ];
    return $cases;
  }

  /**
   * Test building of the item string.
   *
   * @group FabricQuery
   * @dataProvider dataProviderBuildItemString
   */
  public function testBuildItemString($items, $expected) {
    $fabric_query = new FabricQuery('test');
    $fabric_query->setItems($items);
    $actual = $this->callPrivateMethod($fabric_query, 'buildItemString');
    $this->assertEquals($expected, $actual);
  }

  /**
   * Data provider for testBuildItemString.
   */
  public function dataProviderBuildQueryString() {
    $cases = [];
    $cases[] = [
      'plans',
      [
        'Id',
        'planPeriod' => ['items' => ['period' => ['CalendarYear']]],
      ],
      NULL,
      NULL,
      'plans ( first: 10000 ) { items { Id planPeriod { items { period { CalendarYear } } } } endCursor hasNextPage }',
    ];
    $cases[] = [
      'plans',
      [
        'Id',
        'planPeriod' => ['items' => ['period' => ['CalendarYear']]],
      ],
      NULL,
      10,
      'plans ( first: 10 ) { items { Id planPeriod { items { period { CalendarYear } } } } endCursor hasNextPage }',
    ];
    $cases[] = [
      'plans',
      [
        'Id',
        'planPeriod' => ['items' => ['period' => ['CalendarYear']]],
      ],
      [
        'Id' => 1,
      ],
      NULL,
      'plans ( first: 10000, filter: { Id: { eq: 1 } } ) { items { Id planPeriod { items { period { CalendarYear } } } } endCursor hasNextPage }',
    ];
    $cases[] = [
      'plans',
      [
        'Id',
        'planPeriod' => ['items' => ['period' => ['CalendarYear']]],
      ],
      [
        'Id' => 1,
        'RecordStatus' => 'Active',
      ],
      NULL,
      'plans ( first: 10000, filter: { Id: { eq: 1 } RecordStatus: { eq: "Active" } } ) { items { Id planPeriod { items { period { CalendarYear } } } } endCursor hasNextPage }',
    ];
    return $cases;
  }

  /**
   * Test building of the query string.
   *
   * @group FabricQuery
   * @dataProvider dataProviderBuildQueryString
   */
  public function testBuildQueryString($query_name, $items, $filters, $limit, $expected) {
    $fabric_query = new FabricQuery($query_name, $items, $filters, $limit);
    $actual = $fabric_query->buildQueryString();
    $this->assertEquals($expected, $actual);
  }

  /**
   * Data provider for testBuildAggregationQueryString.
   */
  public function dataProviderBuildAggregationQueryString() {
    return [
      [
        'Id',
        ['count' => 'Id'],
        'test ( first: 10000 ) { groupBy(fields: Id) { aggregations { count(field: Id) } } }',
      ],
      [
        ['AttachmentId'],
        ['count' => 'Id'],
        'test ( first: 10000 ) { groupBy(fields: [AttachmentId]) { fields { AttachmentId } aggregations { count(field: Id) } } }',
      ],
    ];
  }

  /**
   * Test building of aggregated query strings.
   *
   * @group FabricQuery
   * @dataProvider dataProviderBuildAggregationQueryString
   */
  public function testBuildAggregationQueryString(array|string $group_field, array $aggregations, string $expected) {
    $fabric_query = new FabricQuery('test');
    $fabric_query->setAggregation($group_field, $aggregations);
    $actual = $fabric_query->buildQueryString();
    $this->assertEquals($expected, $actual);
  }

}
