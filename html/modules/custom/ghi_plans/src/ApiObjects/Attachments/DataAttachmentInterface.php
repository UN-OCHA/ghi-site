<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

/**
 * Interface for API data attachment objects.
 */
interface DataAttachmentInterface extends AttachmentInterface {

  /**
   * Get the fields used in an attachment.
   *
   * @return object[]
   *   An array of field objects as provided by the API.
   */
  public function getOriginalFields();

  /**
   * Get the field types used in an attachment.
   *
   * @return string[]
   *   An array of field types as strings.
   */
  public function getOriginalFieldTypes();

  /**
   * Get the custom id of the attachment.
   *
   * @return string
   *   The custom id of the attachment.
   */
  public function getCustomId();

}
