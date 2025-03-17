<?php

namespace Drupal\ghi_content\RemoteContent;

/**
 * Abstract base class for remote articles.
 */
abstract class RemoteContentBase extends RemoteEntityBase implements RemoteContentInterface {

  /**
   * {@inheritdoc}
   */
  public function getSourceUrl($type = 'canonical') {
    return $this->source->getContentUrl($this->getId(), $type);
  }

  /**
   * {@inheritdoc}
   */
  abstract public function getContentSpaceTags();

  /**
   * {@inheritdoc}
   */
  abstract public function getContentTags();

  /**
   * {@inheritdoc}
   */
  public function getContentSpace() {
    return $this->data->content_space?->title ?? NULL;
  }

}
