<?php

namespace Drupal\Tests\hpc_api\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\hpc_api\Helpers\QueryHelper;

/**
 * @covers Drupal\hpc_api\Helpers\QueryHelper
 */
class QueryHelperTest extends UnitTestCase {

  /**
   * Test endpointCallTimeStorage stores and retrieves values.
   *
   * @group QueryHelper
   */
  public function testEndpointCallTimeStorage() {
    QueryHelper::endpointCallTimeStorage('http://example.com/api', 0.5);
    $result = QueryHelper::endpointCallTimeStorage('http://example.com/api');

    $this->assertSame(0.5, $result);
  }

  /**
   * Test endpointCallTimeStorage returns null for non-existent endpoint.
   *
   * @group QueryHelper
   */
  public function testEndpointCallTimeStorageReturnsNullForUnknownEndpoint() {
    $result = QueryHelper::endpointCallTimeStorage('http://unknown-endpoint.com');

    $this->assertNull($result);
  }

  /**
   * Test endpointCallTimeStorage returns all without arguments.
   *
   * @group QueryHelper
   */
  public function testEndpointCallTimeStorageReturnsAllWhenCalledWithNull() {
    QueryHelper::endpointCallTimeStorage('http://example.com/api', 0.5);
    $result = QueryHelper::endpointCallTimeStorage();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('http://example.com/api', $result);
  }

}
