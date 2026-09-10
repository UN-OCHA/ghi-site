<?php

namespace Drupal\Tests\ghi_content\Unit\Plugin\ConfigurationContainerItem;

use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_content\Plugin\ConfigurationContainerItem\ArticleCollection;
use Drupal\Tests\UnitTestCase;

/**
 * Tests article collection cache dependencies without unnecessary remote loads.
 *
 * @group ghi_content
 */
class ArticleCollectionTest extends UnitTestCase {

  /**
   * Tests that membership cache tags require no article lookup.
   */
  public function testMembershipCacheTagsDoNotLoadArticles(): void {
    $article_manager = $this->createMock(ArticleManager::class);
    $article_manager->expects($this->never())->method('loadNodesForTags');
    $plugin = $this->createPlugin($article_manager);

    $this->assertSame(['node_list:article'], $plugin->getCacheTags());
  }

  /**
   * Tests that only displayed articles contribute remote cache dependencies.
   *
   * @dataProvider displayedArticlesProvider
   */
  public function testDisplayedArticleCacheTags(array $display, array $selected_ids): void {
    $articles = [];
    $expected_tags = ['node_list:article'];
    foreach ([1, 2, 3] as $id) {
      $article = $this->createMock(Article::class);
      $selected = in_array($id, $selected_ids, TRUE);
      $tags = ['node:' . $id, 'hpc_content_module:article:' . $id, 'hpc_content_module:document:' . $id];
      $article->expects($selected ? $this->once() : $this->never())
        ->method('getCacheTags')->willReturn($tags);
      $articles[$id] = $article;
      if ($selected) {
        $expected_tags = array_merge($expected_tags, $tags);
      }
    }
    $article_manager = $this->createMock(ArticleManager::class);
    $article_manager->expects($this->once())->method('loadNodesForTags')
      ->with([7 => 7], NULL, 'AND', NULL, TRUE)->willReturn($articles);
    $plugin = $this->createPlugin($article_manager, $display);

    $build = $plugin->getRenderArray();

    $this->assertSame($selected_ids, array_keys($build['#articles']));
    $this->assertEqualsCanonicalizing($expected_tags, $build['#cache']['tags']);
  }

  /**
   * Provides automatic cards, manually ordered cards, and table display.
   */
  public static function displayedArticlesProvider(): array {
    return [
      'automatic cards' => [['cards' => ['count' => 1]], [1]],
      'manual cards' => [
        ['cards' => ['populate' => 'manual', 'select' => ['selected' => [3, 1], 'order' => [3, 2, 1]]]],
        [3, 1],
      ],
      'table' => [['type' => 'table'], [1, 2, 3]],
    ];
  }

  /**
   * Creates a configured collection with a supplied article manager.
   */
  private function createPlugin(ArticleManager $article_manager, array $display = []): ArticleCollection {
    $plugin = new ArticleCollection([], 'article_collection', []);
    $property = new \ReflectionProperty($plugin, 'articleManager');
    $property->setValue($plugin, $article_manager);
    $plugin->setStringTranslation($this->getStringTranslationStub());
    $plugin->setContext(['section' => NULL]);
    $plugin->setConfig([
      'article_selection_form' => ['tags' => ['tag_ids' => [7]]],
      'display_form' => $display,
    ]);
    return $plugin;
  }

}
