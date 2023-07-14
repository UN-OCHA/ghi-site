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

  /**
   * Get all governing entity objects for the current block instance.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[]
   *   An array of entity objects, aka clusters.
   */
  public function getEntityObjects();

  /**
   * Get all attachment objects for the current block instance.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface[]|null
   *   An array of attachment objects.
   */
  public function getAttachments();

  /**
   * Get the attachment prototype to use for the current block instance.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype|null
   *   The attachment prototype object.
   */
  public function getAttachmentPrototype($attachments = NULL);

}
