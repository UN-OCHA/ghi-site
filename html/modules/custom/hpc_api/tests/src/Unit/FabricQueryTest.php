<?php

namespace Drupal\Tests\hpc_api\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\hpc_api\Query\FabricQuery;
use Drupal\Tests\hpc_api\Traits\PrivateMethodTrait;

/**
 * @covers Drupal\hpc_api\Query\FabricQuery
 *
 * phpcs:disable Squiz.Arrays.ArrayDeclaration.KeySpecified
 */
class FabricQueryTest extends UnitTestCase {

  use PrivateMethodTrait;

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
      'plans ( first: 10000 ) { items { Id planPeriod { items { period { CalendarYear } } } } }',
    ];
    $cases[] = [
      'plans',
      [
        'Id',
        'planPeriod' => ['items' => ['period' => ['CalendarYear']]],
      ],
      NULL,
      10,
      'plans ( first: 10 ) { items { Id planPeriod { items { period { CalendarYear } } } } }',
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
      'plans ( first: 10000, filter: { Id: { eq: 1 } } ) { items { Id planPeriod { items { period { CalendarYear } } } } }',
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
      'plans ( first: 10000, filter: { Id: { eq: 1 } RecordStatus: { eq: "Active" } } ) { items { Id planPeriod { items { period { CalendarYear } } } } }',
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

}
