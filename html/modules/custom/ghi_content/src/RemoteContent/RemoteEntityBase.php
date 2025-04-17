<?php

namespace Drupal\ghi_content\RemoteContent;

use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;

/**
 * Abstract base class for remote articles.
 */
abstract class RemoteEntityBase implements RemoteEntityInterface {

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
  public function getId() {
    return $this->data->id;
  }

}
