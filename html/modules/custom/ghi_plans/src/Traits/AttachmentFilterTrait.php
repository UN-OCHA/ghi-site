<?php

namespace Drupal\ghi_plans\Traits;

use Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachmentInterface;
use Drupal\hpc_api\Query\EndpointQuery;
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
   * Find a suitable plan caseload from the given list of caseloads.
   *
   * This is currently called from \Drupal\ghi_plans\Entity\Plan and from
   * \Drupal\ghi_plans\ApiObjects\Partials with different arguments. The former
   * passes in an array of first-level DataAttachment objects, whereas the
   * latter passes in an array of partial attachment data coming from the plan
   * overview endpoint. This function tries to handle both.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachmentInterface[] $caseloads
   *   A list of caseload attachment objects.
   * @param int $attachment_id
   *   An attachment id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachmentInterface|null
   *   A caseload object or NULL.
   */
  public function findPlanCaseload(array $caseloads, $attachment_id) {
    $caseload = NULL;

    $caseloads = array_filter($caseloads, function ($_caseload) {
      return $_caseload instanceof CaseloadAttachmentInterface;
    });

    if (empty($caseloads)) {
      return $caseload;
    }

    // We have 2 options here. Either a specific attachment has been
    // requested and we use that if it is part of the available attachments.
    if ($attachment_id !== NULL) {
      $matching_caseloads = array_filter($caseloads, function ($caseload) use ($attachment_id) {
        return $caseload->id() == $attachment_id;
      });
      $caseload = !empty($matching_caseloads) ? reset($matching_caseloads) : NULL;
    }

    if (!$caseload) {
      // Or we try to find the real plan level caseload attachment by looking
      // for the ones with PiN data.
      $matching_caseloads = array_filter($caseloads, function ($_caseload) {
        return in_array('inNeed', $_caseload->getOriginalFieldTypes());
      });
      $caseload = !empty($matching_caseloads) ? reset($matching_caseloads) : NULL;
    }

    // Or we try to deduce the suitable attachment by selecting the one with
    // the lowest custom reference.
    if ($caseload === NULL) {
      ArrayHelper::sortObjectsByMethod($caseloads, 'getCustomId', EndpointQuery::SORT_ASC, SORT_STRING);
      $caseload = count($caseloads) ? reset($caseloads) : NULL;
    }
    return $caseload;
  }

}
