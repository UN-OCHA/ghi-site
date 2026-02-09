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
    // Get the attachment data.
    $prototypes = $this->queryWithFilters([
      'Id' => $prototype_id,
    ]);
    if (empty($prototypes)) {
      return NULL;
    }
    $prototype = reset($prototypes);
    return $prototype ? new AttachmentPrototype($prototype) : NULL;
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
    // Get the attachment data.
    $prototypes = $this->queryWithFilters([
      'Id' => $prototype_ids,
    ]);
    if (empty($prototypes)) {
      return [];
    }
    return array_map(fn ($prototype): AttachmentPrototype => new AttachmentPrototype($prototype), $prototypes);
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
    // Get the attachment data.
    $prototypes = $this->queryWithFilters([
      'PlanId' => $plan_id,
    ]);
    if (empty($prototypes)) {
      return NULL;
    }
    $prototype = $prototypes[$prototype_id] ?? NULL;
    return $prototype ? new AttachmentPrototype($prototype) : NULL;
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
    // Get the attachment data.
    $prototypes = $this->queryWithFilters([
      'PlanId' => $plan_id,
      'Type' => AttachmentPrototype::DATA_TYPES,
    ]);
    return $this->buildResultObjects($prototypes, AttachmentPrototype::class);
  }

}
