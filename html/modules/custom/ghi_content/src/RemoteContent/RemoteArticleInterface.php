<?php

namespace Drupal\ghi_content\RemoteContent;

/**
 * Interface class for remote sources.
 */
interface RemoteArticleInterface {

  /**
   * Get the raw data of the article.
   *
   * @return array
   *   The raw data for the article.
   */
  public function getRawData();

  /**
   * Get the source of the article.
   *
   * @return \Drupal\ghi_content\RemoteSource\RemoteSourceInterface
   *   The article source.
   */
  public function getSource();

  /**
   * Get a url to the source of the article.
   *
   * @param string $type
   *   The type of link. Defaults to "canonical".
   *
   * @return \Drupal\core\Url
   *   A url object.
   */
  public function getSourceUrl($type = 'canonical');

  /**
   * Get the id of the article.
   *
   * @return int
   *   The article id.
   */
  public function getId();

  /**
   * Get the title of the article.
   *
   * @return string
   *   The article title.
   */
  public function getTitle();

  /**
   * Get the updated time of the article.
   *
   * @return int
   *   A timestamp.
   */
  public function getUpdated();

  /**
   * Get the summary text of the article.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   The article summary.
   */
  public function getSummary();

  /**
   * Get the articles header image.
   *
   * @return string
   *   The URI of the image.
   */
  public function getImageUri();

  /**
   * Get the articles header images credits.
   *
   * @return string
   *   The credits for the image.
   */
  public function getImageCredits();

  /**
   * Get the articles header images caption.
   *
   * @return object
   *   The caption object for the image.
   */
  public function getImageCaption();

  /**
   * Get the articles header images caption as plain text.
   *
   * @return string
   *   The caption for the image.
   */
  public function getImageCaptionPlain();

  /**
   * Get the articles header images caption as markup.
   *
   * @param bool $add_credits
   *   Flag indicating if the credits should be added to the end of the text.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The markup for an image caption.
   */
  public function getImageCaptionMarkup($add_credits = FALSE);

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
   * Get the major tags.
   *
   * @return array
   *   The major tags.
   */
  public function getMajorTags();

  /**
   * Get the minor tags.
   *
   * @return object[]
   *   The minor tags.
   */
  public function getMinorTags();

}
