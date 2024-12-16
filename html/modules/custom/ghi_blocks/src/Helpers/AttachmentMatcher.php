<?php

namespace Drupal\ghi_blocks\Helpers;

use Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype;
use Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;

/**
 * Helper function for attachment matching.
 */
class AttachmentMatcher {

  /**
   * Match an array of data attachments against an original attachment.
   *
   * This checks the attachment type and the attachment source to find
   * attachments that correspond in their function.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $original_attachment
   *   The original attachment to match against.
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment[] $available_attachments
   *   The attachments to match.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment[]
   *   The result set of matched attachments.
   */
  public static function matchDataAttachments(AttachmentInterface $original_attachment, array $available_attachments) {
    return array_filter($available_attachments, function (DataAttachment $attachment) use ($original_attachment) {
      if ($original_attachment->getType() != $attachment->getType()) {
        // Check the attachment type, e.g. "caseload" vs "indicator".
        return FALSE;
      }
      if ($original_attachment->source->entity_type != $attachment->source->entity_type) {
        // Check the source entity type, e.g. "governingEntity" vs "plan".
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
   * Match a data point index on the given attachments.
   *
   * Matching is done by type, such that a data point of attachment 2 is
   * returned that has the same type as the given data point in attachment 1.
   *
   * @param int $data_point_index
   *   The data point index to match.
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment_1
   *   The first or original attachment.
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment_2
   *   The second or new attachment.
   *
   * @return int
   *   Either the original index if no match can be found or a new index.
   */
  public static function matchDataPointOnAttachments($data_point_index, DataAttachment $attachment_1, DataAttachment $attachment_2) {
    // Reload the prototypes, because depending on how the attachments have
    // been loaded, they might not have the full attachment prototype set up,
    // some are missing the calculated fields.
    // E.g. plan/:ID?content=entities .
    $prototype_1 = $attachment_1->getPrototype()?->id() ? self::getPrototype($attachment_1->getPrototype()->id()) : NULL;
    $prototype_2 = $attachment_2->getPrototype()?->id() ? self::getPrototype($attachment_2->getPrototype()->id()) : NULL;
    if (!$prototype_1 || !$prototype_2) {
      return $data_point_index;
    }
    return self::matchDataPointOnAttachmentPrototypes($data_point_index, $prototype_1, $prototype_2);
  }

  /**
   * Match a data point index on the given attachment prototypes.
   *
   * @param int $data_point_index
   *   The data point index to match.
   * @param \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype $prototype_1
   *   The first or original attachment prototype.
   * @param \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype $prototype_2
   *   The second or new attachment prototype.
   *
   * @return int
   *   Either the original index if no match can be found or a new index.
   */
  public static function matchDataPointOnAttachmentPrototypes($data_point_index, AttachmentPrototype $prototype_1, AttachmentPrototype $prototype_2) {
    // First get the original and the new fields. These are the types keyed by
    // the field index.
    $original_fields = $prototype_1->getFieldTypes();
    $new_fields = $prototype_2->getFieldTypes();
    if (!array_key_exists($data_point_index, $original_fields)) {
      // This is fishy.
      return $data_point_index;
    }

    // Compare the types.
    if ($original_fields[$data_point_index] == ($new_fields[$data_point_index] ?? NULL)) {
      // If they are the same, there is no need to go further.
      return $data_point_index;
    }
    // It's referring to a different type now, let's see if we can find the
    // same as the original type in the set of new fields.
    $new_index = array_search($original_fields[$data_point_index], $new_fields);

    // We either found a new index and can return it, or we didn't and we
    // return the original.
    return $new_index !== FALSE ? $new_index : $data_point_index;
  }

  /**
   * Fetch prototype data from the API.
   *
   * @param int $prototype_id
   *   The id of the attachment prototype to load.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype|null
   *   An attachment prototype object.
   */
  private static function getPrototype($prototype_id) {
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentPrototypeQuery $query_handler */
    $query_handler = \Drupal::service('plugin.manager.endpoint_query_manager')->createInstance('attachment_prototype_query');
    if (!$query_handler) {
      return NULL;
    }
    return $query_handler->getPrototypeById($prototype_id);
  }

}
