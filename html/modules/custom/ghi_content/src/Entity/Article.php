<?php

namespace Drupal\ghi_content\Entity;

/**
 * Bundle class for section nodes.
 */
class Article extends ContentBase {

  /**
   * Get the current context node.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The context node if set.
   */
  public function getContextNode() {
    if (!$this->contextNode) {
      $document = $this->getCurrentDocumentNode();
      if ($document && $this->isValidContextNode($document)) {
        $this->setContextNode($document);
      }
    }
    return parent::getContextNode();
  }

  /**
   * {@inheritdoc}
   */
  public function isValidContextNode($node) {
    if ($node instanceof Document) {
      return $node->hasArticle($this);
    }
    return parent::isValidContextNode($node);
  }

  /**
   * Get the document chapter to which this article belongs.
   *
   * This assumes that every article can only appear once per document.
   *
   * @param \Drupal\ghi_content\Entity\Document $document
   *   The document node.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteChapterInterface
   *   The chapter object.
   */
  public function getDocumentChapter(Document $document) {
    foreach ($document->getChapters() as $chapter) {
      $articles = $document->getChapterArticles($chapter);
      foreach ($articles as $article) {
        if ($article->id() == $this->id()) {
          return $chapter;
        }
      }
    }
    return NULL;
  }

}
