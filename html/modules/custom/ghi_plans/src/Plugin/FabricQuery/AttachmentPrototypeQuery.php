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
    $payload = "
      attachmentPrototypes (filter: {
        Id:  {
          eq: {$prototype_id}
        }
      }) {
        items { " . AttachmentPrototype::GRAPHQL_DIMENSION_ITEMS . "}
      }";
    $data = $this->fabricQuery->query($payload);
    $prototypes = $this->getItems($data, 'attachmentPrototypes');
    if (empty($prototypes)) {
      return NULL;
    }
    $prototype = reset($prototypes);
    return $prototype ? new AttachmentPrototype($prototype) : NULL;
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
    $payload = "
      attachmentPrototypes (filter: {
        PlanId:  { eq: {$plan_id} }
      }) {
        items { " . AttachmentPrototype::GRAPHQL_DIMENSION_ITEMS . " }
      }";
    $data = $this->fabricQuery->query($payload);
    $prototypes = $this->getItems($data, 'attachmentPrototypes');
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
    $types = '"' . implode('", "', AttachmentPrototype::DATA_TYPES) . '"';
    $payload = "
      attachmentPrototypes (filter: {
        PlanId: { eq: {$plan_id} }
        and: [{ Type: { in: [{$types}] } }]
      }) {
        items { " . AttachmentPrototype::GRAPHQL_DIMENSION_ITEMS . " }
      }";
    $data = $this->fabricQuery->query($payload);
    return $this->buildResultObjectsFromData($data, 'attachmentPrototypes', AttachmentPrototype::class);
  }

}
