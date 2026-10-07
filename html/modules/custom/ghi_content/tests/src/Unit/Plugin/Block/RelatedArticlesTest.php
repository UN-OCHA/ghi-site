<?php

namespace Drupal\Tests\ghi_content\Unit\Plugin\Block;

use Drupal\ghi_blocks\Interfaces\ConfigurationUpdateInterface;
use Drupal\ghi_content\Plugin\Block\RelatedArticles;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Related Articles block plugin.
 *
 * @group ghi_content
 */
final class RelatedArticlesTest extends UnitTestCase {

  /**
   * Tests migration of the legacy article selection configuration.
   */
  public function testUpdateConfiguration(): void {
    $plugin = (new \ReflectionClass(RelatedArticles::class))->newInstanceWithoutConstructor();
    $configuration = new \ReflectionProperty(RelatedArticles::class, 'configuration');
    $configuration->setValue($plugin, [
      'hpc' => [
        'select' => [
          'selected' => [10, 20],
        ],
        'label' => 'Related updates',
      ],
    ]);

    $this->assertInstanceOf(ConfigurationUpdateInterface::class, $plugin);
    $this->assertTrue($plugin->updateConfiguration());
    $this->assertSame([
      'articles' => [
        'article_select' => [
          'entity_ids' => ['node:10', 'node:20'],
        ],
      ],
      'display' => [
        'label' => 'Related updates',
      ],
    ], $configuration->getValue($plugin)['hpc']);
    $this->assertFalse($plugin->updateConfiguration());
  }

  /**
   * Tests migration of an empty legacy article selection.
   */
  public function testUpdateEmptyConfiguration(): void {
    $plugin = (new \ReflectionClass(RelatedArticles::class))->newInstanceWithoutConstructor();
    $configuration = new \ReflectionProperty(RelatedArticles::class, 'configuration');
    $configuration->setValue($plugin, [
      'hpc' => [
        'articles' => [
          'article_select' => [
            'entity_ids' => [],
          ],
        ],
        'display' => [],
        'select' => [
          'selected' => [],
        ],
        'label' => NULL,
      ],
    ]);

    $this->assertTrue($plugin->updateConfiguration());
    $this->assertSame([
      'articles' => [
        'article_select' => [
          'entity_ids' => [],
        ],
      ],
      'display' => [
        'label' => NULL,
      ],
    ], $configuration->getValue($plugin)['hpc']);
    $this->assertFalse($plugin->updateConfiguration());
  }

}
