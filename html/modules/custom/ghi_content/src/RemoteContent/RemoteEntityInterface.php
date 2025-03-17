<?php

namespace Drupal\ghi_content\RemoteContent;

/**
 * Interface class for remote content.
 */
interface RemoteEntityInterface {

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
   * Get the id of the article.
   *
   * @return int
   *   The article id.
   */
  public function getId();

}
