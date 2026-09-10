<?php

namespace Drupal\Tests\ghi_content\Unit\Plugin\Block;

use Drupal\ghi_content\Plugin\Block\ArticleCollection;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests that tab dependencies are retained by the block content cache.
 *
 * @group ghi_content
 */
class ArticleCollectionTest extends UnitTestCase {

  /**
   * Tests membership and displayed article dependencies on the block cache.
   */
  public function testTabCacheDependencies(): void {
    $item = $this->createMock(ConfigurationContainerItemPluginInterface::class);
    $item->method('getCacheTags')->willReturn(['node_list:article']);
    $item->method('getLabel')->willReturn('Articles');
    $item->method('getRenderArray')->willReturn([
      '#markup' => 'Article cards',
      '#cache' => ['tags' => ['node:1', 'hpc_content_module:document:2']],
    ]);
    $block = $this->getMockBuilder(ArticleCollection::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getBlockConfig', 'getBlockContext', 'getConfiguredItems', 'getItemTypePluginForColumn'])
      ->getMock();
    $block->method('getBlockConfig')->willReturn([]);
    $block->method('getBlockContext')->willReturn([]);
    $block->method('getConfiguredItems')->willReturn([['id' => 'article_collection']]);
    $block->method('getItemTypePluginForColumn')->willReturn($item);

    $build = $block->buildContent();

    $this->assertEqualsCanonicalizing(['node_list:article', 'node:1', 'hpc_content_module:document:2'], $build['#cache']['tags']);
    $this->assertSame('Articles', $build[0]['#tabs'][0]['title']['#markup']);
  }

}
