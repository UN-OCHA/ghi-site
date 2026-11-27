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
   * The field storing outstanding review reason identifiers.
   */
  const REVIEW_REASONS_FIELD = 'field_review_reasons';

  const REVIEW_REASON_PARAGRAPH_ORDER = 'paragraph_order';
  const REVIEW_REASON_PARAGRAPHS_ADDED = 'paragraphs_added';
  const REVIEW_REASON_IMAGE_DOWNLOAD = 'image_download';
  const REVIEW_REASON_MANUAL = 'manual';

  /**
   * Set or get the value of the needs review flag.
   *
   * @param bool|null $state
   *   If given, the needs review value will be set to this state.
   * @param string|null $reason
   *   A review reason identifier to add when flagging. Defaults to a manual
   *   review. Clearing the flag also clears all outstanding reasons.
   *
   * @return bool|void
   *   If no argument is given, returns the value of the needs review flag.
   */
  public function needsReview(?bool $state = NULL, ?string $reason = NULL);

  /**
   * Get the translated outstanding review reasons.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   Review reason labels keyed by their identifiers.
   */
  public function getReviewReasons(): array;

}
