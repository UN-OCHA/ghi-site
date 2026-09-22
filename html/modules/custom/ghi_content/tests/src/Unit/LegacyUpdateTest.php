<?php

namespace Drupal\Tests\ghi_content\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests that old update hooks retain their original filesystem targets.
 *
 * @group ghi_content
 */
class LegacyUpdateTest extends UnitTestCase {

  /**
   * Tests directory preparation without touching the filesystem or database.
   */
  public function testThumbnailDirectory(): void {
    require_once dirname(__DIR__, 3) . '/ghi_content.install';
    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->expects($this->once())->method('prepareDirectory')
      ->with('public://thumbnails/article', FileSystemInterface::CREATE_DIRECTORY)
      ->willReturn(TRUE);
    $container = new ContainerBuilder();
    $container->set('file_system', $file_system);
    \Drupal::setContainer($container);

    $sandbox = [];
    ghi_content_update_9001($sandbox);
  }

}
