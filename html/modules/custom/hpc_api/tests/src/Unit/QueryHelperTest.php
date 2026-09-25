<?php

namespace Drupal\Tests\hpc_api\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\hpc_api\Helpers\QueryHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the query helper.
 */
#[CoversClass(QueryHelper::class)]
class QueryHelperTest extends UnitTestCase {

  /**
   * Test endpointCallTimeStorage stores and retrieves values.
   */
  #[Group('QueryHelper')]
  public function testEndpointCallTimeStorage() {
    QueryHelper::endpointCallTimeStorage('http://example.com/api', 0.5);
    $result = QueryHelper::endpointCallTimeStorage('http://example.com/api');

    $this->assertSame(0.5, $result);
  }

  /**
   * Test endpointCallTimeStorage returns null for non-existent endpoint.
   */
  #[Group('QueryHelper')]
  public function testEndpointCallTimeStorageReturnsNullForUnknownEndpoint() {
    $result = QueryHelper::endpointCallTimeStorage('http://unknown-endpoint.com');

    $this->assertNull($result);
  }

  /**
   * Test endpointCallTimeStorage returns all without arguments.
   */
  #[Group('QueryHelper')]
  public function testEndpointCallTimeStorageReturnsAllWhenCalledWithNull() {
    QueryHelper::endpointCallTimeStorage('http://example.com/api', 0.5);
    $result = QueryHelper::endpointCallTimeStorage();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('http://example.com/api', $result);
  }

}
