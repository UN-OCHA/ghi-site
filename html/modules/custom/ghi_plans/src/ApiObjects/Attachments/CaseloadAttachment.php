<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

/**
 * Abstraction for API caseload attachment objects.
 */
class CaseloadAttachment extends Attachment implements CaseloadAttachmentInterface {

  /**
   * Get a caseload value.
   *
   * @param string $metric_type
   *   The metric type.
   * @param string $metric_name
   *   The english metric name.
   *
   * @return float
   *   The caseload value if found.
   */
  public function getCaseloadValue($metric_type, $metric_name = NULL): ?float {
    $values = $this->getCurrentValues();
    return $values[$metric_type] ?? NULL;
  }

}
