<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

use Drupal\Core\Render\Markup;

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

  /**
   * Get the content of the text attachment.
   *
   * @return string
   *   The raw content of the attachment.
   */
  public function getContent() {
    return $this->content;
  }

  /**
   * Get the content of the text attachment as a markup object.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   The content of the attachment as a markup object.
   */
  public function getMarkup() {
    return Markup::create($this->getContent());
  }

}
