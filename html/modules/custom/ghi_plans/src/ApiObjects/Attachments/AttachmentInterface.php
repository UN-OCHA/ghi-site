<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

use Drupal\hpc_api\ApiObjects\ApiObjectInterface;

/**
 * Interface for API attachment objects.
 */
interface AttachmentInterface extends ApiObjectInterface {

  /**
   * Get a title for the attachment.
   *
   * @return string
   *   The attachment title.
   */
  public function getTitle();

}
