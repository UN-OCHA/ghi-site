<?php

namespace Drupal\ghi_plans\Traits;

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
  private function filterAttachments(array $attachments, array $filter) {
    return ArrayHelper::filterArray($attachments, $this->prepareAttachmentFilter($filter));
  }

}
