<?php

namespace Drupal\ghi_content\RemoteContent;

/**
 * Abstract base class for remote articles.
 */
abstract class RemoteArticleBase implements RemoteArticleInterface {

  /**
   * Source system.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceInterface
   */
  protected $source;

  /**
   * {@inheritdoc}
   */
  public function getSource() {
    return $this->source;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceUrl($type = 'canonical') {
    return $this->source->getContentUrl($this->getId(), $type);
  }

}
