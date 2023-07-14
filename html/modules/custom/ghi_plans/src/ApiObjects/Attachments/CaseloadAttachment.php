<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

/**
 * Abstraction for API data attachment objects.
 */
class CaseloadAttachment extends DataAttachment {

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->getPrototype()->getName();
  }

}
