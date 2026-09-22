<?php

namespace Drupal\Tests\ghi_content\Unit\Import;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\Import\ImportManager;
use Drupal\ghi_content\RemoteContent\RemoteContentImageInterface;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests image imports using the file replacement policy.
 *
 * @group ghi_content
 * @coversDefaultClass \Drupal\ghi_content\Import\ImportManager
 */
class ImportImageTest extends UnitTestCase {

  /**
   * Tests that importing an image replaces an existing file at its destination.
   *
   * @covers ::importImage
   */
  public function testImportImage() {
    $source = $this->createMock(RemoteSourceInterface::class);
    $source->method('getFileSize')->willReturn(10);
    $source->method('getFileContent')->willReturn('image data');
    $content = $this->createMock(RemoteContentImageInterface::class);
    $content->method('getSource')->willReturn($source);
    $content->method('getImageUri')->willReturn('https://example.com/image.png');
    $content->method('getImageCaptionPlain')->willReturn('Image caption');

    $field = $this->createMock(FileFieldItemList::class);
    $field->method('isEmpty')->willReturn(TRUE);
    $field->expects($this->once())->method('setValue')->with([
      'target_id' => 7,
      'alt' => 'Image caption',
      'title' => NULL,
    ]);
    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')->with('field_image')->willReturn(TRUE);
    $node->method('get')->with('field_image')->willReturn($field);

    $file = $this->createMock(FileInterface::class);
    $file->method('id')->willReturn(7);
    $repository = $this->createMock(FileRepositoryInterface::class);
    $repository->expects($this->once())->method('writeData')
      ->with('image data', ArticleManager::IMAGE_DIRECTORY . '/image.png', FileExists::Replace)
      ->willReturn($file);

    $manager = new ImportManager(
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(BlockManagerInterface::class),
      $this->createMock(AccountInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(LayoutTempstoreRepositoryInterface::class),
      $this->createMock(SelectionPluginManager::class),
      $repository,
      new EventDispatcher(),
    );
    $manager->setStringTranslation($this->getStringTranslationStub());
    $manager->importImage($node, $content);
  }

}
