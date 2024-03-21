<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

/**
 * Abstraction for API file attachment objects.
 */
class FileAttachment extends AttachmentBase {

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $attachment = $this->getRawData();
    $attachment_version = $attachment->attachmentVersion ?? NULL;
    return (object) [
      'id' => $attachment->id,
      'type' => strtolower($attachment->type),
      'url' => $attachment_version?->value?->file->url ?? NULL,
      'title' => $attachment_version?->value?->file->title ?? '',
      'file_name' => $attachment_version?->value->name ?? '',
      'credit' => $attachment_version?->value->credit ?? '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->title;
  }

  /**
   * Get the url for this attachment.
   */
  public function getUrl() {
    return $this->url;
  }

  /**
   * Get the credit for this attachment.
   */
  public function getCredit() {
    return $this->credit;
  }

}
