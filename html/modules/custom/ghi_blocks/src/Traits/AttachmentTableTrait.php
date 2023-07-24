<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype;
use Drupal\ghi_plans\ApiObjects\Entities\PlanEntity;
use Drupal\hpc_api\ApiObjects\ApiObjectInterface;

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
  public function getEntityObjects() {
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
  public function getAttachments() {
    $entities = $this->getEntityObjects();
    if (empty($entities)) {
      return [];
    }
    return $this->getAttachmentsForEntities($entities);
  }

  /**
   * Get the attachment prototype to use for the current block instance.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype|null
   *   The attachment prototype object.
   */
  public function getAttachmentPrototype($attachments = NULL) {
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
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface[] $attachments
   *   The attachment objects.
   *
   * @return array
   *   An array of prototype names, keyed by the prototype id.
   */
  public function getUniquePrototypes(array $attachments = NULL) {
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

  /**
   * Filter the given set of attachment prototypes by supported entity types.
   *
   * This looks at the attachment prototypes list of supported entity type ref
   * codes and compares that to entity type ref codes of the given set of plan
   * entities.
   *
   * @param \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype[] $attachment_prototypes
   *   An array of attachment prototype objects to filter.
   * @param \Drupal\hpc_api\ApiObjects\ApiObjectInterface[] $plan_entities
   *   An array of plan entity objects to use for filtering.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype[]
   *   The filtered list of attachment prototypes.
   */
  public function filterAttachmentPrototypesByPlanEntities(array $attachment_prototypes, array $plan_entities) {
    $entity_type_ref_codes = array_unique(array_map(function (ApiObjectInterface $entity) {
      return $entity->getEntityTypeRefCode();
    }, $plan_entities));
    $attachment_prototypes = array_filter($attachment_prototypes, function (AttachmentPrototype $prototype) use ($entity_type_ref_codes) {
      if (empty($prototype->getEntityRefCodes())) {
        return FALSE;
      }
      return array_intersect($prototype->getEntityRefCodes(), $entity_type_ref_codes);
    });

    return $attachment_prototypes;
  }

  /**
   * Build a "contributes to" heading.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Entities\PlanEntity $entity
   *   The plan entity.
   *
   * @return array|null
   *   A render array or NULL.
   */
  public function buildContributesToHeading(PlanEntity $entity) {
    $parents = $entity->getPlanEntityParents();
    if (empty($parents)) {
      return NULL;
    }
    $contribute_items = array_map(function (PlanEntity $plan_entity) {
      return [
        [
          '#theme' => 'hpc_icon',
          '#icon' => 'check_circle',
          '#tag' => 'span',
        ],
        [
          '#markup' => $plan_entity->getEntityName(),
        ],
      ];
    }, $parents);
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['plan-entity-contribution-wrapper'],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t('Contributes to'),
      ],
      [
        '#theme' => 'item_list',
        '#items' => $contribute_items,
      ],
    ];
  }

}
