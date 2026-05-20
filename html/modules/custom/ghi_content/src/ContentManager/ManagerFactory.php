<?php

namespace Drupal\ghi_content\ContentManager;

use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_content\Entity\Document;

/**
 * Manager factory for content manager services.
 */
class ManagerFactory {

  /**
   * The article manager.
   *
   * @var \Drupal\ghi_content\ContentManager\ArticleManager
   */
  protected $articleManager;

  /**
   * The article manager.
   *
   * @var \Drupal\ghi_content\ContentManager\DocumentManager
   */
  protected $documentManager;

  /**
   * Constructs a document manager.
   */
  public function __construct(ArticleManager $article_manager, DocumentManager $document_manager) {
    $this->articleManager = $article_manager;
    $this->documentManager = $document_manager;
  }

  /**
   * Get the appropriate content manager for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Drupal\ghi_content\ContentManager\BaseContentManager
   *   The content manager.
   */
  public function getContentManager($node) {
    if ($node instanceof Article) {
      return $this->articleManager;
    }
    if ($node instanceof Document) {
      return $this->documentManager;
    }
    return NULL;
  }

  /**
   * Get the content manager for an incoming remote content type.
   *
   * @param string $type
   *   The remote content type.
   *
   * @return \Drupal\ghi_content\ContentManager\BaseContentManager|null
   *   The content manager.
   */
  public function getContentManagerForRemoteType(string $type): ?BaseContentManager {
    return match ($type) {
      'article' => $this->articleManager,
      'document' => $this->documentManager,
      default => NULL,
    };
  }

}
