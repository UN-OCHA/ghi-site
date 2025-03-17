<?php

namespace Drupal\ghi_content\RemoteContent\HpcContentModule;

use Drupal\Component\Serialization\Yaml;
use Drupal\ghi_content\RemoteContent\RemoteEntityBase;
use Drupal\ghi_content\RemoteContent\RemoteParagraphInterface;

/**
 * Defines a RemoteParagraph object.
 */
class RemoteParagraph extends RemoteEntityBase implements RemoteParagraphInterface {

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
    return !empty($this->data->configuration) ? Yaml::decode($this->data->configuration) : [];
  }

}
