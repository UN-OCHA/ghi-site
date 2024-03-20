<?php

namespace Drupal\ghi_content\RemoteContent;

use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;

/**
 * Abstract base class for remote articles.
 */
abstract class RemoteContentBase implements RemoteContentInterface {

  /**
   * Source system.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceInterface
   */
  protected $source;

  /**
   * Raw article data from the remote source.
   *
   * @var mixed
   */
  protected $data;

  /**
   * Construct a new RemoteArticle object.
   */
  public function __construct($data, RemoteSourceInterface $source) {
    $this->data = $data;
    $this->source = $source;
  }

  /**
   * {@inheritdoc}
   */
  public function getRawData() {
    return (array) $this->data;
  }

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

  /**
   * {@inheritdoc}
   */
  abstract public function getMajorTags();

  /**
   * {@inheritdoc}
   */
  abstract public function getMinorTags();

  /**
   * {@inheritdoc}
   */
  public function getContentSpace() {
    return $this->data->content_space?->title ?? NULL;
  }

}
