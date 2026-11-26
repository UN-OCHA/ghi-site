<?php

namespace Drupal\Tests\ghi_content\Unit\Plugin\Block;

use Drupal\ghi_content\Plugin\Block\DocumentChapter;
use Drupal\ghi_content\Plugin\Block\Paragraph;
use Drupal\ghi_content\RemoteContent\RemoteArticleInterface;
use Drupal\ghi_content\RemoteContent\RemoteChapterInterface;
use Drupal\ghi_content\RemoteContent\RemoteDocumentInterface;
use Drupal\ghi_content\RemoteContent\RemoteParagraphInterface;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests content blocks resolve references without loading remote responses.
 */
#[Group('ghi_content')]
class RemoteContentItemBlockTest extends UnitTestCase {

  /**
   * Provides the paragraph and chapter block types.
   */
  public static function contentItemBlockProvider(): array {
    return ['paragraph' => [FALSE], 'chapter' => [TRUE]];
  }

  /**
   * Tests ownership and item resolution use only the supplied snapshot.
   */
  #[DataProvider('contentItemBlockProvider')]
  public function testResolvesSuppliedContentWithoutLoading(bool $chapter): void {
    $configuration = $chapter ? [
      'document_select' => ['document' => ['remote_source' => 'content', 'document_id' => 42]],
      'chapter' => ['chapter_id' => 7],
    ] : [
      'article_select' => ['article' => ['remote_source' => 'content', 'article_id' => 42]],
      'paragraph' => ['paragraph_id' => [7]],
    ];
    $load_method = $chapter ? 'getDocument' : 'getArticle';
    $block = $this->getMockBuilder($chapter ? DocumentChapter::class : Paragraph::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getBlockConfig', $load_method])
      ->getMock();
    $block->method('getBlockConfig')->willReturnCallback(function () use (&$configuration): array {
      return $configuration;
    });
    $block->expects($this->never())->method($load_method);
    $source = $this->createMock(RemoteSourceInterface::class);
    $source->method('getPluginId')->willReturn('content');
    $snapshot_type = $chapter ? RemoteDocumentInterface::class : RemoteArticleInterface::class;
    $snapshot = $this->createMock($snapshot_type);
    $snapshot->method('getSource')->willReturn($source);
    $snapshot->method('getId')->willReturn(42);
    $item = $this->createMock($chapter ? RemoteChapterInterface::class : RemoteParagraphInterface::class);
    $snapshot->expects($this->once())->method($chapter ? 'getChapter' : 'getParagraph')->with(7)->willReturn($item);

    $this->assertTrue($block->referencesRemoteContent($snapshot));
    $this->assertSame($item, $block->getRemoteContentItem($snapshot));
    $foreign = $this->createMock($snapshot_type);
    $foreign->method('getSource')->willReturn($source);
    $foreign->method('getId')->willReturn(84);
    $this->assertFalse($block->referencesRemoteContent($foreign));
    $this->assertNull($block->getRemoteContentItem($foreign));

    $other_source = $this->createMock(RemoteSourceInterface::class);
    $other_source->method('getPluginId')->willReturn('other');
    $foreign_source = $this->createMock($snapshot_type);
    $foreign_source->method('getSource')->willReturn($other_source);
    $foreign_source->method('getId')->willReturn(42);
    $this->assertFalse($block->referencesRemoteContent($foreign_source));
    $this->assertNull($block->getRemoteContentItem($foreign_source));

    // An absent selection still references its parent, but resolves no item.
    $configuration[$chapter ? 'chapter' : 'paragraph'] = [];
    $this->assertTrue($block->referencesRemoteContent($snapshot));
    $this->assertNull($block->getRemoteContentItem($snapshot));
  }

}
