<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

/**
 * Abstraction for API contact attachment objects.
 */
class ContactAttachment extends AttachmentBase {

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $attachment = $this->getRawData();
    return (object) [
      'id' => $attachment->id,
      'type' => strtolower($attachment->type),
      'name' => $attachment->attachmentVersion->value->contactName ?? NULL,
      'mail' => $attachment->attachmentVersion->value->contactEmail ?? NULL,
      'agency' => $attachment->attachmentVersion->value->leadAgency ?? NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->name;
  }

}
