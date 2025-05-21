<?php

namespace Drupal\ghi_content\Entity;

/**
 * Interface for content entites that support a simple review logic.
 */
interface ContentReviewInterface {

  /**
   * The name of the protected field.
   */
  const NEEDS_REVIEW_FIELD = 'field_needs_review';

  /**
   * Set or get the value of the needs review flag.
   *
   * @param bool|null $state
   *   If given, the needs review value will be set to this state.
   *
   * @return bool|void
   *   If no argument is given, returns the value of the needs review flag.
   */
  public function needsReview(?bool $state = NULL);

}
