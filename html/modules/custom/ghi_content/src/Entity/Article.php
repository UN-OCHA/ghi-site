<?php

namespace Drupal\ghi_content\Entity;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Bundle class for section nodes.
 */
class Article extends ContentBase {

  /**
   * Get the chapter of the article.
   *
   * @return string|null
   *   The chapter as a plain string or NULL.
   */
  public function getChapter() {
    return $this->get('field_chapter')->value ?? NULL;
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

  /**
   * {@inheritdoc}
   */
  public function getPageMetaData() {
    $metadata = [];
    $metadata[] = [
      '#markup' => new TranslatableMarkup('Published on @date', [
        '@date' => $this->getDateFormatter()->format($this->getCreatedTime(), 'custom', 'j F Y'),
      ]),
    ];
    $tags = $this->getDisplayTags();
    if (!empty($tags)) {
      $metadata[] = [
        '#markup' => new TranslatableMarkup('Keywords @keywords', [
          '@keywords' => implode(', ', $tags),
        ]),
      ];
    }
    if ($this->isPublished()) {
      $metadata[] = [
        '#theme' => 'social_links',
      ];
    }
    return $metadata;
  }

}
