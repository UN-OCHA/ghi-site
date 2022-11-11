<?php

namespace Drupal\ghi_blocks\Traits;

/**
 * Trait with common logic for attachment based tables.
 */
trait AttachmentTableTrait {

  /**
   * Get all attachment objects for the given entities.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface[]
   *   An array of attachment objects.
   */
  abstract public function getAttachmentsForEntities(array $entities, $prototype_id = NULL);

  /**
   * Get all governing entity objects for the current block instance.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[]
   *   An array of entity objects, aka clusters.
   */
  private function getEntityObjects() {
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery $query */
    $query = $this->getQueryHandler('entities');
    return $query->getPlanEntities($this->getPageNode(), 'governing');
  }

  /**
   * Get all attachment objects for the current block instance.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface[]|null
   *   An array of attachment objects.
   */
  private function getAttachments() {
    $entities = $this->getEntityObjects();
    if (empty($entities)) {
      return NULL;
    }
    return $this->getAttachmentsForEntities($entities);
  }

  /**
   * Get the attachment prototype to use for the current block instance.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype|null
   *   The attachment prototype object.
   */
  private function getAttachmentPrototype($attachments = NULL) {
    $conf = $this->getBlockConfig();
    $prototype_id = NULL;
    foreach ($conf as $values) {
      if (array_key_exists('prototype_id', $values)) {
        $prototype_id = $values['prototype_id'];
      }
    }
    if (!$prototype_id) {
      $prototypes = $this->getUniquePrototypes($attachments);
      $prototype_id = $prototypes ? array_key_first($prototypes) : NULL;
    }
    if (!$prototype_id) {
      return NULL;
    }
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentPrototypeQuery $query */
    $query = $this->getQueryHandler('attachment_prototype');
    return $query->getPrototypeByPlanAndId($this->getCurrentPlanId(), $prototype_id);
  }

  /**
   * Get unique prototype options for the available attachments of this block.
   *
   * @return array
   *   An array of prototype names, keyed by the prototype id.
   */
  private function getUniquePrototypes($attachments = NULL) {
    $attachments = $attachments ?? ($this->getAttachments() ?? []);
    $prototype_opions = [];
    foreach ($attachments as $attachment) {
      $prototype = $attachment->prototype;
      if (array_key_exists($prototype->id, $prototype_opions)) {
        continue;
      }
      $prototype_opions[$prototype->id] = $prototype;
    }
    return $prototype_opions;
  }

}
