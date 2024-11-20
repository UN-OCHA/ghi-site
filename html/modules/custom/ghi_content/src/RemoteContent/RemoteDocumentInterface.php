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
   * Get the summary text of the document.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   The document summary.
   */
  public function getSummary();

  /**
   * Get the documents header image.
   *
   * @return string
   *   The URI of the image.
   */
  public function getImageUri();

  /**
   * Get the documents header images credits.
   *
   * @return string
   *   The credits for the image.
   */
  public function getImageCredits();

  /**
   * Get the documents header images caption.
   *
   * @return object
   *   The caption object for the image.
   */
  public function getImageCaption();

  /**
   * Get the documents header images caption as plain text.
   *
   * @return string
   *   The caption for the image.
   */
  public function getImageCaptionPlain();

  /**
   * Get the documents header images caption as markup.
   *
   * @param bool $add_credits
   *   Flag indicating if the credits should be added to the end of the text.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The markup for an image caption.
   */
  public function getImageCaptionMarkup($add_credits = FALSE);

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
