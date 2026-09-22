<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ghi_blocks\Helpers\GlobalMapHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the global map helper.
 */
#[CoversClass(GlobalMapHelper::class)]
class GlobalMapHelperTest extends UnitTestCase {

  /**
   * Tests that the helper can translate the map disclaimer without a block.
   */
  public function testGetDefaultMapDisclaimer() {
    $helper = new GlobalMapHelper();
    $helper->setStringTranslation($this->getStringTranslationStub());
    $this->assertStringContainsString('United Nations', $helper->getDefaultMapDisclaimer());
  }

  /**
   * Test getStyleUrl returns correct URL format.
   */
  #[Group('GlobalMapHelper')]
  public function testGetStyleUrl() {
    $result = GlobalMapHelper::getStyleUrl();

    $this->assertIsString($result);
    $this->assertStringStartsWith('mapbox://styles/', $result);
    $this->assertStringContainsString('?optimize=true', $result);
  }

  /**
   * Test getMapConfigCacheTags returns expected cache tags.
   */
  #[Group('GlobalMapHelper')]
  public function testGetMapConfigCacheTags() {
    $result = GlobalMapHelper::getMapConfigCacheTags();

    $this->assertIsArray($result);
    $this->assertContains('config:ghi_blocks.map_settings', $result);
  }

}
