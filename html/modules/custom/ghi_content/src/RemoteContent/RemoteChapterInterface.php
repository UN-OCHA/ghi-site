<?php

namespace Drupal\ghi_content\RemoteContent;

/**
 * Interface class for remote chapters.
 */
interface RemoteChapterInterface extends RemoteContentItemBaseInterface {

  /**
   * Get the title of the chapter.
   *
   * @return string
   *   The chapter title.
   */
  public function getTitle();

  /**
   * Get the short title of the chapter.
   *
   * @return string
   *   The chapter short title.
   */
  public function getShortTitle();

  /**
   * Get the summary text of the document.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   The document summary.
   */
  public function getSummary();

  /**
   * Whether the chapter is hidden from the document.
   *
   * @return bool
   *   TRUE if the chapter is marked as hidden, FALSE otherwhise.
   */
  public function isHidden();

  /**
   * Get the ids of the articles inside a chapter.
   *
   * @return int[]
   *   The ids of the articles.
   */
  public function getArticleIds();

  /**
   * Get the articles inside a chapter.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteArticleInterface[]
   *   The articles.
   */
  public function getArticles();

}
