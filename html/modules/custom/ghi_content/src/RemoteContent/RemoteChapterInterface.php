<?php

namespace Drupal\ghi_content\RemoteContent;

/**
 * Interface class for remote chapters.
 */
interface RemoteChapterInterface {

  /**
   * Get the source of the chapter.
   *
   * @return \Drupal\ghi_content\RemoteSource\RemoteSourceInterface
   *   The paragraph source.
   */
  public function getSource();

  /**
   * Get the id of the paragraph.
   *
   * @return int
   *   The paragraph id.
   */
  public function getId();

  /**
   * Get the uuid for the paragraph.
   *
   * @return string
   *   The paragraph uuid.
   */
  public function getUuid();

  /**
   * Get the rendered content of the paragraph.
   *
   * @return string
   *   The rendered paragraph content.
   */
  public function getRendered();

  /**
   * Get the configuration of the paragraph.
   *
   * @return object
   *   The paragraph configuration.
   */
  public function getConfiguration();

}
