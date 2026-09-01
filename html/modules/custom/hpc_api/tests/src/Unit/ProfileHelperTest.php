<?php

namespace Drupal\Tests\hpc_api\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\hpc_api\Helpers\ProfileHelper;

/**
 * @covers Drupal\hpc_api\Helpers\ProfileHelper
 */
class ProfileHelperTest extends UnitTestCase {

  /**
   * Test profileStart returns a key.
   *
   * @group ProfileHelper
   */
  public function testProfileStartReturnsKey() {
    $key = ProfileHelper::profileStart('test_operation');
    $this->assertNotEmpty($key);
    $this->assertSame('test_operation', $key);
  }

  /**
   * Test profileEnd completes a profile.
   *
   * @group ProfileHelper
   */
  public function testProfileEnd() {
    ProfileHelper::profileStart('test_operation');
    ProfileHelper::profileEnd('test_operation');

    $summary = ProfileHelper::profileSummary();
    $this->assertArrayHasKey('test_operation', $summary);
    $this->assertSame(ProfileHelper::STATE_END, $summary['test_operation']['state']);
  }

  /**
   * Test profileSummary returns array.
   *
   * @group ProfileHelper
   */
  public function testProfileSummaryReturnsArray() {
    ProfileHelper::profileStart('test_op');
    ProfileHelper::profileEnd('test_op');

    $summary = ProfileHelper::profileSummary();
    $this->assertIsArray($summary);
  }

  /**
   * Test duplicate keys get incremented.
   *
   * @group ProfileHelper
   */
  public function testDuplicateKeysIncremented() {
    $key1 = ProfileHelper::profileStart('duplicate');
    $key2 = ProfileHelper::profileStart('duplicate');

    $this->assertSame('duplicate', $key1);
    $this->assertSame('duplicate_0', $key2);
  }

}
