<?php

namespace Drupal\ghi_plans\Traits;

use Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface;
use Drupal\hpc_common\Helpers\ArrayHelper;

/**
 * Helper trait classes that need to filter attachments.
 */
trait AttachmentFilterTrait {

  /**
   * Map attachment types to their tring representation in the API.
   *
   * @param string $type
   *   The type used in GHI.
   *
   * @return string
   *   The type used in the API.
   */
  private function mapAttachmentType($type) {
    $type_map = [
      'caseload' => 'caseLoad',
    ];
    return !empty($type_map[$type]) ? $type_map[$type] : $type;
  }

  /**
   * Preare attachment filters.
   *
   * @param array $filter
   *   The passed in filter array.
   *
   * @return array
   *   A prepared filter array.
   */
  private function prepareAttachmentFilter(array $filter) {
    if (empty($filter)) {
      return $filter;
    }
    $filter = array_filter($filter, function ($item) {
      return $item !== NULL;
    });
    if (!empty($filter['type'])) {
      $filter['type'] = array_map([$this, 'mapAttachmentType'], (array) $filter['type']);
    }
    if (!empty($filter['prototype_id'])) {
      $filter['attachmentPrototypeId'] = $filter['prototype_id'];
      unset($filter['prototype_id']);
    }
    return $filter;
  }

  /**
   * Filter the given list of attachments.
   *
   * @param array $attachments
   *   The attachments to filter.
   * @param array $filter
   *   The passed in filter array.
   *
   * @return array
   *   An array with the attachments who match the filter.
   */
  public function filterAttachments(array $attachments, array $filter) {
    return ArrayHelper::filterArray($attachments, $this->prepareAttachmentFilter($filter));
  }

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
  public function matchDataAttachments(AttachmentInterface $original_attachment, array $available_attachments) {
    return array_filter($available_attachments, function ($attachment) use ($original_attachment) {
      if ($original_attachment->getType() != $attachment->getType()) {
        return FALSE;
      }
      if ($original_attachment->source->entity_type != $attachment->source->entity_type) {
        return FALSE;
      }
      return TRUE;
    });
  }

}
