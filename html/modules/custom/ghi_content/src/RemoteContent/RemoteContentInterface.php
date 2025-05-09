<?php

namespace Drupal\ghi_content\RemoteContent;

/**
 * Interface class for remote content.
 */
interface RemoteContentInterface extends RemoteEntityInterface {

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
   * Get the title of the article.
   *
   * @return string
   *   The article title.
   */
  public function getTitle();

  /**
   * Get the created time of the article.
   *
   * @return int
   *   A timestamp.
   */
  public function getCreated();

  /**
   * Get the updated time of the article.
   *
   * @return int
   *   A timestamp.
   */
  public function getUpdated();

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

  /**
   * Get the content space.
   *
   * @return string
   *   The name of the content space.
   */
  public function getContentSpace();

}
