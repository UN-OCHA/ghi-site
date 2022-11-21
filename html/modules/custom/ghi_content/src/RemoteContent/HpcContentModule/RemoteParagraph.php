<?php

namespace Drupal\ghi_content\RemoteContent\HpcContentModule;

use Drupal\Component\Serialization\Yaml;
use Drupal\ghi_content\RemoteContent\RemoteParagraphInterface;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;

/**
 * Defines a RemoteParagraph object.
 */
class RemoteParagraph implements RemoteParagraphInterface {

  /**
   * Raw article data from the remote source.
   *
   * @var mixed
   */
  private $data;

  /**
   * Source system.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceInterface
   */
  protected $source;

  /**
   * Construct a new RemoteParagraph object.
   */
  public function __construct($data, RemoteSourceInterface $source) {
    $this->data = $data;
    $this->source = $source;
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

  /**
   * {@inheritdoc}
   */
  public function getUuid() {
    return $this->data->uuid;
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->data->type;
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeLabel() {
    return $this->data->typeLabel;
  }

  /**
   * {@inheritdoc}
   */
  public function getPromoted() {
    return !empty($this->data->promoted);
  }

  /**
   * {@inheritdoc}
   */
  public function getRendered() {
    return $this->getSource()->changeRessourceLinks($this->data->rendered);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->data->configuration ? Yaml::decode($this->data->configuration) : [];
  }

}
