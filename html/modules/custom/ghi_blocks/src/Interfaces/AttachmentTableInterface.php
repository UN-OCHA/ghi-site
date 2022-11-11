<?php

namespace Drupal\ghi_blocks\Interfaces;

/**
 * Interface for blocks showing attachments in a table.
 */
interface AttachmentTableInterface {

  /**
   * Get the attachments for the given entities.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[] $entities
   *   The entity objects.
   * @param int $prototype_id
   *   An optional prototype id to filter for.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface[]
   *   The matching (processed) attachment objects, keyed by the attachment id.
   */
  public function getAttachmentsForEntities(array $entities, $prototype_id = NULL);

}
