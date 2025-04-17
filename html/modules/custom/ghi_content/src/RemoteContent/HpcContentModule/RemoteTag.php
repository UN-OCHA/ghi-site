<?php

namespace Drupal\ghi_content\RemoteContent\HpcContentModule;

use Drupal\ghi_content\RemoteContent\RemoteEntityBase;
use Drupal\ghi_content\RemoteContent\RemoteTagInterface;

/**
 * Defines a RemoteTag object.
 */
class RemoteTag extends RemoteEntityBase implements RemoteTagInterface {

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->data?->type;
  }

}
