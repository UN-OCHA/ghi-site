<?php

namespace Drupal\Tests\ghi_content\Unit;

use Drupal\ghi_content\RemoteContent\HpcContentModule\RemoteArticle;
use Drupal\ghi_content\RemoteContent\HpcContentModule\RemoteDocument;
use Drupal\ghi_content\RemoteContent\RemoteContentImageInterface;
use Drupal\ghi_content\RemoteContent\RemoteDocumentInterface;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests image captions from the HPC Content Module.
 */
#[Group('ghi_content')]
class RemoteContentImageTest extends UnitTestCase {

  /**
   * Tests markup for complete, partial, and empty caption data.
   */
  #[DataProvider('captionMarkupProvider')]
  public function testImageCaptionMarkup(string $class, ?object $caption, ?string $credits, bool $add_credits, ?string $expected): void {
    $content = $this->createRemoteContent($class, $caption, $credits);
    $markup = $content->getImageCaptionMarkup($add_credits);

    if ($expected === NULL) {
      $this->assertNull($markup);
      return;
    }
    $this->assertSame($expected, (string) $markup);
  }

  /**
   * Provides caption markup cases for remote articles and documents.
   */
  public static function captionMarkupProvider(): array {
    $scenarios = [
      'complete caption' => [
        (object) [
          'location' => 'Nairobi',
          'text' => 'Image caption',
        ],
        'OCHA',
        TRUE,
        '<h6 class="location">Nairobi</h6><p class="text">Image caption <span class="credits">OCHA</span></p>',
      ],
      'location only' => [
        (object) ['location' => 'Nairobi'],
        NULL,
        FALSE,
        '<h6 class="location">Nairobi</h6>',
      ],
      'text and credits without location' => [
        (object) ['text' => 'Image caption'],
        'OCHA',
        TRUE,
        '<p class="text">Image caption <span class="credits">OCHA</span></p>',
      ],
      'NULL caption values with credits' => [
        (object) [
          'location' => NULL,
          'text' => NULL,
        ],
        'OCHA',
        TRUE,
        '<p class="text"><span class="credits">OCHA</span></p>',
      ],
      'missing caption with credits' => [
        NULL,
        'OCHA',
        TRUE,
        '<p class="text"><span class="credits">OCHA</span></p>',
      ],
      'no requested display values' => [
        (object) [],
        'OCHA',
        FALSE,
        NULL,
      ],
    ];

    $cases = [];
    foreach ([
      'article' => RemoteArticle::class,
      'document' => RemoteDocument::class,
    ] as $type => $class) {
      foreach ($scenarios as $scenario => $arguments) {
        $cases[$type . ': ' . $scenario] = [$class, ...$arguments];
      }
    }
    return $cases;
  }

  /**
   * Tests plain captions tolerate individually missing values.
   */
  #[DataProvider('remoteContentClassProvider')]
  public function testImageCaptionPlainHandlesMissingValues(string $class): void {
    $content = $this->createRemoteContent($class, (object) ['text' => 'Image caption']);

    $this->assertSame('Image caption', $content->getImageCaptionPlain());
  }

  /**
   * Provides the remote content image implementations.
   */
  public static function remoteContentClassProvider(): array {
    return [
      'article' => [RemoteArticle::class],
      'document' => [RemoteDocument::class],
    ];
  }

  /**
   * Tests that documents expose image handling as a separate capability.
   */
  public function testRemoteDocumentImageCapability(): void {
    $document = $this->createRemoteContent(RemoteDocument::class, NULL);

    $this->assertInstanceOf(RemoteDocumentInterface::class, $document);
    $this->assertInstanceOf(RemoteContentImageInterface::class, $document);
  }

  /**
   * Creates remote content with image metadata.
   */
  private function createRemoteContent(string $class, ?object $caption, ?string $credits = NULL): RemoteContentImageInterface {
    $data = (object) [
      'id' => 1,
      'title' => 'Test content',
      'image' => (object) ['credits' => $credits],
    ];
    if ($caption !== NULL) {
      $data->imageCaption = $caption;
    }
    return new $class($data, $this->createMock(RemoteSourceInterface::class));
  }

}
