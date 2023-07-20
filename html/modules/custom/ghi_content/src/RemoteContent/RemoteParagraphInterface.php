<?php

namespace Drupal\ghi_content\RemoteContent;

/**
 * Interface class for remote paragraphs.
 */
interface RemoteParagraphInterface extends RemoteContentItemBaseInterface {

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
   * Get the promoted status of the paragraph.
   *
   * @return bool
   *   The promoted status.
   */
  public function getPromoted();

  /**
   * Get the rendered content of the paragraph.
   *
   * @return string
   *   The rendered paragraph content.
   */
  public function getRendered();

}
