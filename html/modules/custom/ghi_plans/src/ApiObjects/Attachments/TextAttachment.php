<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

/**
 * Abstraction for API text attachment objects.
 */
class TextAttachment extends AttachmentBase {

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $attachment = $this->getRawData();
    return (object) [
      'id' => $attachment->id,
      'type' => strtolower($attachment->type),
      'title' => $attachment->attachmentVersion->value->name ?? '',
      'content' => html_entity_decode($attachment->attachmentVersion->value->content ?? ''),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->title;
  }

}
