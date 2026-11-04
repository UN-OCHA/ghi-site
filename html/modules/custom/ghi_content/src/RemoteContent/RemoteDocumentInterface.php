<?php

namespace Drupal\ghi_content\RemoteContent;

/**
 * Interface class for remote sources.
 */
interface RemoteDocumentInterface extends RemoteContentInterface {

  /**
   * Get the short title for the document.
   *
   * @return string
   *   The short title.
   */
  public function getShortTitle();

  /**
   * Get the chapters of the document.
   *
   * @param bool $include_hidden
   *   Whether to fetch hidden chapters or not.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteChapterInterface[]
   *   The document chapters.
   */
  public function getChapters($include_hidden = TRUE);

  /**
   * Get the chapters of the document.
   *
   * @param int $id
   *   The chapter id on the remote.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteChapterInterface
   *   The document chapter.
   */
  public function getChapter($id);

  /**
   * Get the chapter number according to the document structure.
   *
   * @param int $id
   *   The chapter id on the remote.
   *
   * @return int|false
   *   The chapter number, starting at 1, or FALSE.
   */
  public function getChapterNumber($id);

  /**
   * Get the summary text of the document.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   The document summary.
   */
  public function getSummary();

  /**
   * Get the content space tags.
   *
   * @return array
   *   The content space tags.
   */
  public function getContentSpaceTags();

  /**
   * Get the tags.
   *
   * @return object[]
   *   The tags.
   */
  public function getContentTags();

}
