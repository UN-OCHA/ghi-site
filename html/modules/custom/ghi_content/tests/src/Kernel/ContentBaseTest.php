<?php

namespace Drupal\Tests\ghi_content\Kernel;

use Drupal\ghi_content\Entity\Article;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests some features of the abstract ContentBase class.
 *
 * @group ghi_content
 */
class ContentBaseTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'taxonomy',
    'field',
    'layout_builder',
    'layout_discovery',
    'migrate',
    'text',
    'filter',
    'file',
    'token',
    'path',
    'path_alias',
    'pathauto',
    'ghi_sections',
    'ghi_content',
  ];

  const ARTICLE_BUNDLE = 'article';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('file');
    $this->installEntitySchema('path_alias');
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'taxonomy', 'field', 'file', 'pathauto']);

    NodeType::create(['type' => self::ARTICLE_BUNDLE])->save();
  }

  /**
   * Tests that nodes that have been manually unpublished can be identified.
   */
  public function testUnpublishedManually() {
    $article = Article::create([
      'title' => 'Title',
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);
    $article->save();
    $this->assertTrue($article->unpublishedManually());

    $article->setNewRevision();
    $article->save();
    $this->assertFalse($article->unpublishedManually());

    $article->setPublished();
    $article->setNewRevision();
    $article->save();
    $this->assertFalse($article->unpublishedManually());

    $article->setUnpublished();
    $article->setNewRevision();
    $article->save();
    $this->assertTrue($article->unpublishedManually());
  }

}
