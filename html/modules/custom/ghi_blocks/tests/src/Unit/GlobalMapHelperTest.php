<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ghi_blocks\Helpers\GlobalMapHelper;

/**
 * @covers Drupal\ghi_blocks\Helpers\GlobalMapHelper
 */
class GlobalMapHelperTest extends UnitTestCase {

  /**
   * Test getStyleUrl returns correct URL format.
   *
   * @group GlobalMapHelper
   */
  public function testGetStyleUrl() {
    $result = GlobalMapHelper::getStyleUrl();

    $this->assertIsString($result);
    $this->assertStringStartsWith('mapbox://styles/', $result);
    $this->assertStringContainsString('?optimize=true', $result);
  }

  /**
   * Test getMapConfigCacheTags returns expected cache tags.
   *
   * @group GlobalMapHelper
   */
  public function testGetMapConfigCacheTags() {
    $result = GlobalMapHelper::getMapConfigCacheTags();

    $this->assertIsArray($result);
    $this->assertContains('config:ghi_blocks.map_settings', $result);
  }

}
