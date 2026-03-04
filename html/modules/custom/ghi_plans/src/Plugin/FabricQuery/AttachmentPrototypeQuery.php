<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'attachment_prototype' fabric query.
 */
#[FabricQuery(
  id: 'attachment_prototype',
  label: new TranslatableMarkup('Attachment prototype query'),
)]
class AttachmentPrototypeQuery extends FabricQueryBase {

  /**
   * Internal helper to query attachment prototypes with the given filters.
   *
   * @param array $filters
   *   An associative array of filters.
   *
   * @return false|object|array
   *   The result from the fabric query or FALSE on failure.
   */
  private function queryWithFilters($filters): false|object|array {
    return $this->fabricClient->createQuery('attachmentPrototypes', AttachmentPrototype::getGraphQlItems())
      ->setFilters($filters + [
        'RecordStatus' => 'Active',
      ])
      ->execute();

  }

  /**
   * Get an attachment prototype by its id.
   *
   * @param int $prototype_id
   *   The attachment prototype id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype|null
   *   The attachment prototype object or NULL if not found.
   */
  public function getPrototype(int $prototype_id): ?AttachmentPrototype {
    $prototype = $this->objectStore->getObject($prototype_id, AttachmentPrototype::getObjectStorageKey());
    if ($prototype) {
      return $prototype;
    }

    // Get the attachment data.
    $items = $this->queryWithFilters([
      'Id' => $prototype_id,
    ]);
    if (empty($items)) {
      return NULL;
    }
    $item = reset($items);
    $prototype = $item ? new AttachmentPrototype($item) : NULL;
    if ($prototype) {
      $this->objectStore->addObject($prototype);
    }
    return $prototype;
  }

  /**
   * Get attachment prototypes by ids.
   *
   * @param int[] $prototype_ids
   *   An array of attachment prototype ids.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype[]
   *   The attachment prototype object or NULL if not found.
   */
  public function getPrototypes(array $prototype_ids): array {
    $prototype_ids = array_unique($prototype_ids);
    $prototypes = $this->objectStore->getObjects($prototype_ids, AttachmentPrototype::getObjectStorageKey());
    if (count($prototypes) == count($prototype_ids)) {
      return $prototypes;
    }
    $prototype_ids = array_diff($prototype_ids, array_keys($prototypes));

    // Get the attachment data.
    $items = $this->queryWithFilters([
      'Id' => $prototype_ids,
    ]);
    if (empty($items)) {
      return [];
    }
    $prototypes = array_map(fn ($prototype): AttachmentPrototype => new AttachmentPrototype($prototype), $items);
    $this->objectStore->addObjects($prototypes);
    return $prototypes;
  }

  /**
   * Get an attachment prototype by plan and prototype ID.
   *
   * @param int $plan_id
   *   The id of the plan to which a prototype belongs.
   * @param int $prototype_id
   *   The id of the prototype.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype|null
   *   The processed attachment prototype object.
   */
  public function getPrototypeByPlanAndId(int $plan_id, int $prototype_id): ?AttachmentPrototype {
    // Get the attachment data prototypes.
    $prototypes = $this->getDataPrototypesForPlan($plan_id);
    return $prototypes[$prototype_id] ?? NULL;
  }

  /**
   * Get all data attachment prototypes for the given plan.
   *
   * @param int $plan_id
   *   The plan id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype[]
   *   An array of attachment prototype objects.
   */
  public function getDataPrototypesForPlan($plan_id) {
    $prototypes = $this->objectStore->getObjectCollection(AttachmentPrototype::getObjectStorageKey(), 'PlanId', $plan_id);
    if (!empty($prototypes)) {
      return $prototypes;
    }
    // Get the attachment data.
    $items = $this->queryWithFilters([
      'PlanId' => $plan_id,
      'Type' => AttachmentPrototype::DATA_TYPES,
    ]);
    $prototypes = $this->buildResultObjects($items, AttachmentPrototype::class);
    $this->objectStore->addObjectCollection($prototypes, AttachmentPrototype::getObjectStorageKey(), 'PlanId');
    return $prototypes;
  }

  /**
   * Get all data attachment prototypes for the given plan.
   *
   * @param int[] $plan_ids
   *   The plan ids.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype[][]
   *   An array of arrays of attachment prototype objects, keyed by plan id.
   */
  public function getDataPrototypesForPlans(array $plan_ids) {
    sort($plan_ids);
    // Get the attachment data.
    $items = $this->queryWithFilters([
      'PlanId' => $plan_ids,
      'Type' => AttachmentPrototype::DATA_TYPES,
    ]);
    /** @var \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype[] $prototypes */
    $prototypes = $this->buildResultObjects($items, AttachmentPrototype::class);
    $this->objectStore->addObjectCollection($prototypes, AttachmentPrototype::getObjectStorageKey(), 'PlanId');
    $prototypes_by_plan = [];
    foreach ($prototypes as $prototype) {
      $plan_id = $prototype->getPlanId();
      $prototypes_by_plan = [];
      $prototypes_by_plan[$plan_id] = $prototypes_by_plan[$plan_id] ?? [];
      $prototypes_by_plan[$plan_id][$prototype->id()] = $prototype;
    }
    return $prototypes_by_plan;
  }

}
