<?php

namespace Drupal\ghi_content\RemoteContent;

/**
 * Interface class for remote content.
 */
interface RemoteContentImageInterface extends RemoteContentInterface {

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

}
