<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

/**
 * Interface for API data attachment objects.
 */
interface DataAttachmentInterface extends AttachmentInterface {

  /**
   * Get the fields used in an attachment.
   *
   * @return string[]
   *   An array of field labels as provided by the API.
   */
  public function getFields();

  /**
   * Get the field types used in an attachment.
   *
   * @return string[]
   *   An array of field types as strings.
   */
  public function getFieldTypes();

  /**
   * Get the custom id of the attachment.
   *
   * @return string
   *   The custom id of the attachment.
   */
  public function getCustomId();

  /**
   * Get the custom id prefixed with the ref code.
   *
   * @return string
   *   The custom id prefixed with the ref code.
   */
  public function getCustomIdWithRefCode(): string;

  /**
   * Get the composed reference.
   *
   * @return string
   *   The composed reference.
   */
  public function getComposedReference(): string;

  /**
   * Extract the plan id from an attachment object.
   *
   * @return int|null
   *   The plan ID if any can be found.
   */
  public function getPlanId();

  /**
   * Get the source entity type.
   *
   * @return string|null
   *   The source entity type.
   */
  public function getSourceEntityType();

  /**
   * Get the source entity id.
   *
   * @return string|null
   *   The source entity id.
   */
  public function getSourceEntityId();

}
