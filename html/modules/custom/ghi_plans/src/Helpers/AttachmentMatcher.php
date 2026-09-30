<?php

namespace Drupal\ghi_plans\Helpers;

use Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\Traits\PlanQueryTrait;

/**
 * Helper function for attachment matching.
 */
class AttachmentMatcher {

  use PlanQueryTrait;

  /**
   * Match an array of data attachments against an original attachment.
   *
   * This checks the attachment type and the attachment source to find
   * attachments that correspond in their function.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $original_attachment
   *   The original attachment to match against.
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment[] $available_attachments
   *   The attachments to match.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment[]
   *   The result set of matched attachments.
   */
  public static function matchAttachments(AttachmentInterface $original_attachment, array $available_attachments) {
    return array_filter($available_attachments, function (Attachment $attachment) use ($original_attachment) {
      if ($original_attachment->getAttachmentType() != $attachment->getAttachmentType()) {
        // Check the attachment type, e.g. "caseload" vs "indicator".
        return FALSE;
      }
      if ($original_attachment->getSourceEntityType() != $attachment->getSourceEntityType()) {
        // Check the source entity type, e.g. cluster vs plan.
        return FALSE;
      }
      if ($original_attachment->getPrototype()->getRefCode() != $attachment->getPrototype()->getRefCode()) {
        // Check the attachment prototype ref code, e.g. "BP" vs "BF.
        return FALSE;
      }
      return TRUE;
    });
  }

  /**
   * Match a data point on the given attachments.
   *
   * Matching is done by type, such that a data point of attachment 2 is
   * returned that has the same type as the given data point in attachment 1.
   *
   * @param string|int|null $data_point
   *   The metric type to match, or a numeric index from legacy configuration.
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment_1
   *   The first or original attachment.
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment_2
   *   The second or new attachment.
   *
   * @return string|null
   *   The matching metric type, or NULL if no match can be found.
   */
  public static function matchDataPointOnAttachments($data_point, Attachment $attachment_1, Attachment $attachment_2): ?string {
    // Reload the prototypes, because depending on how the attachments have
    // been loaded, they might not have the full attachment prototype set up,
    // some are missing the calculated fields.
    // E.g. plan/:ID?content=entities .
    $prototype_1 = $attachment_1->getPrototype()?->id() ? self::getPrototype($attachment_1->getPrototype()->id()) : NULL;
    $prototype_2 = $attachment_2->getPrototype()?->id() ? self::getPrototype($attachment_2->getPrototype()->id()) : NULL;
    if (!$prototype_1 || !$prototype_2) {
      return is_string($data_point) && !is_numeric($data_point) ? $data_point : NULL;
    }
    return self::matchDataPointOnAttachmentPrototypes($data_point, $prototype_1, $prototype_2);
  }

  /**
   * Match a data point on the given attachment prototypes.
   *
   * @param string|int|null $data_point
   *   The metric type to match, or a numeric index from legacy configuration.
   * @param \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype $prototype_1
   *   The first or original attachment prototype.
   * @param \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype $prototype_2
   *   The second or new attachment prototype.
   *
   * @return string|null
   *   The matching metric type, or NULL if no match can be found.
   */
  public static function matchDataPointOnAttachmentPrototypes($data_point, AttachmentPrototype $prototype_1, AttachmentPrototype $prototype_2): ?string {
    $metric_type = $prototype_1->resolveMetricType($data_point);

    return is_string($metric_type) && in_array($metric_type, $prototype_2->getFieldTypes(), TRUE) ? $metric_type : NULL;
  }

  /**
   * Fetch prototype data from the API.
   *
   * @param int $prototype_id
   *   The id of the attachment prototype to load.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype|null
   *   An attachment prototype object.
   */
  private static function getPrototype($prototype_id) {
    return self::getAttachmentPrototypeQuery()?->getPrototype($prototype_id) ?? NULL;
  }

}
