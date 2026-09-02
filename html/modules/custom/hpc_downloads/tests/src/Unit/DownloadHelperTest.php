<?php

namespace Drupal\Tests\hpc_downloads\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\hpc_downloads\Helpers\DownloadHelper;
use Drupal\Core\Render\Markup;

/**
 * @covers Drupal\hpc_downloads\Helpers\DownloadHelper
 */
class DownloadHelperTest extends UnitTestCase {

  /**
   * Test getDownloadIconMarkup returns markup.
   *
   * @group DownloadHelper
   */
  public function testGetDownloadIconMarkup() {
    $result = DownloadHelper::getDownloadIconMarkup();
    $this->assertInstanceOf(Markup::class, $result);
    $this->assertSame('<span class="download-icon"></span>', (string) $result);
  }

  /**
   * Test getDownloadIconMarkup returns non-empty string.
   *
   * @group DownloadHelper
   */
  public function testGetDownloadIconMarkupIsNotEmpty() {
    $result = DownloadHelper::getDownloadIconMarkup();
    $this->assertNotEmpty((string) $result);
  }

  /**
   * Test getDownloadIconMarkup contains download-icon class.
   *
   * @group DownloadHelper
   */
  public function testGetDownloadIconMarkupContainsClass() {
    $result = DownloadHelper::getDownloadIconMarkup();
    $this->assertStringContainsString('download-icon', (string) $result);
  }

}
