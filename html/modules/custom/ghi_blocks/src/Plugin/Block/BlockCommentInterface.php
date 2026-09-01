<?php

namespace Drupal\ghi_blocks\Plugin\Block;

/**
 * Interface for blocks that can have comments.
 */
interface BlockCommentInterface {

  /**
   * Get the comment.
   *
   * @return string|null
   *   The comment as a string or NULL.
   */
  public function getBlockComment(): ?string;

}
