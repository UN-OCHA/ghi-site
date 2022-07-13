<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

use Drupal\hpc_api\ApiObjects\ApiObjectBase;

/**
 * Base class for API attachment objects.
 */
abstract class AttachmentBase extends ApiObjectBase implements AttachmentInterface {

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return NULL;
  }

}
