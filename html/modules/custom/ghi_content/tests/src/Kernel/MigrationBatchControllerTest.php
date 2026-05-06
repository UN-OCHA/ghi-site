<?php

namespace Drupal\Tests\ghi_content\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ghi_content\Controller\MigrationBatchController;
use Drupal\ghi_content\Entity\Article;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;

/**
 * Tests migration cleanup publishing decisions.
 *
 * @group ghi_content
 */
class MigrationBatchControllerTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'migrate',
    'layout_builder',
    'layout_discovery',
    'text',
    'filter',
    'file',
    'ghi_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'field', 'file']);

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();
  }

  /**
   * Tests that remote autoVisible controls cleanup republishes.
   */
  public function testShouldRepublishNodeRespectsAutoVisible() {
    $article = Article::create([
      'type' => 'article',
      'title' => 'Imported hidden article',
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);
    $article->save();

    // A second hidden revision mirrors a synced update after the initial
    // import and must not be treated as a manual unpublish.
    $article->setNewRevision();
    $article->save();

    $this->assertFalse($article->unpublishedManually());
    $this->assertFalse($this->invokeShouldRepublishNode($article, [
      'autoVisible' => FALSE,
    ]));
    $this->assertTrue($this->invokeShouldRepublishNode($article, [
      'autoVisible' => TRUE,
    ]));
  }

  /**
   * Tests that manual unpublishes still block cleanup republishes.
   */
  public function testShouldRepublishNodeKeepsManualUnpublishes() {
    $article = Article::create([
      'type' => 'article',
      'title' => 'Manually hidden article',
      'status' => NodeInterface::PUBLISHED,
    ]);
    $article->save();

    $article->setUnpublished();
    $article->setNewRevision();
    $article->save();

    $this->assertTrue($article->unpublishedManually());
    $this->assertFalse($this->invokeShouldRepublishNode($article, [
      'autoVisible' => TRUE,
    ]));
  }

  /**
   * Invoke the protected helper under test.
   *
   * @param \Drupal\ghi_content\Entity\Article $article
   *   The local content node.
   * @param array|null $source_row
   *   The source metadata row.
   *
   * @return bool
   *   TRUE if the node should be republished.
   */
  protected function invokeShouldRepublishNode(Article $article, ?array $source_row = NULL) {
    $method = new \ReflectionMethod(MigrationBatchController::class, 'shouldRepublishNode');
    $method->setAccessible(TRUE);
    return $method->invoke(NULL, $article, $source_row);
  }

}
