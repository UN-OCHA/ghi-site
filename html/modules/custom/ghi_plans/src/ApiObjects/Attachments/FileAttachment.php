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
    return (object) [
      'id' => $attachment->id,
      'type' => strtolower($attachment->type),
      'url' => $attachment->attachmentVersion->value->file->url,
      'title' => $attachment->attachmentVersion->value->file->title ?? '',
      'file_name' => $attachment->attachmentVersion->value->name ?? '',
      'credit' => $attachment->attachmentVersion->value->credit ?? '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->title;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl() {
    return $this->url;
  }

  /**
   * {@inheritdoc}
   */
  public function getCredit() {
    return $this->credit;
  }

}
