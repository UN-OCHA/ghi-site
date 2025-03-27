<?php

namespace Drupal\ghi_content\RemoteContent;

use Drupal\ghi_content\RemoteContent\HpcContentModule\RemoteArticle;

/**
 * Interface class for remote articles.
 */
interface RemoteArticleInterface extends RemoteContentInterface {

  /**
   * Get the short title for the document.
   *
   * @return string
   *   The short title.
   */
  public function getShortTitle();

  /**
   * Get the section of the article.
   *
   * @return string
   *   The article section.
   */
  public function getSection();

  /**
   * Get the summary text of the article.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   The article summary.
   */
  public function getSummary();

  /**
   * Get a paragraph by id.
   *
   * @param int $id
   *   The id of the paragraph on the remote.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteParagraphInterface
   *   The paragraph object.
   */
  public function getParagraph($id);

  /**
   * Get all paragraphs.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteParagraphInterface[]
   *   The paragraph objects.
   */
  public function getParagraphs();

  /**
   * Check if an article has the given article as a sub-article.
   *
   * @param \Drupal\ghi_content\RemoteContent\HpcContentModule\RemoteArticle $remote_article
   *   The remote article to check as being a sub-article.
   *
   * @return bool
   *   TRUE if the given remote article is a sub-article of the current remote
   *   article, FALSE otherwise.
   */
  public function hasSubarticle(RemoteArticle $remote_article);

}
