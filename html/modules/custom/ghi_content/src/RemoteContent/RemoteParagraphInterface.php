<?php

namespace Drupal\ghi_content\RemoteContent;

/**
 * Interface class for remote paragraphs.
 */
interface RemoteParagraphInterface {

  /**
   * Get the source of the paragraph.
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
   * Get the type string of the paragraph.
   *
   * @return string
   *   The paragraph type.
   */
  public function getType();

  /**
   * Get the type label of the paragraph.
   *
   * @return string
   *   The paragraph type label.
   */
  public function getTypeLabel();

  /**
   * Get the rendered content of the paragraph.
   *
   * @return string
   *   The rendered paragraph content.
   */
  public function getRendered();

}
