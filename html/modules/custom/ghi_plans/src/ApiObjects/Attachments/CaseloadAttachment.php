<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

/**
 * Abstraction for API caseload attachment objects.
 */
class CaseloadAttachment extends DataAttachment implements CaseloadAttachmentInterface {

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
    if (!$this->hasValues()) {
      return NULL;
    }
    foreach ($this->getTotals() as $total) {
      if (!$total->getMetric()) {
        continue;
      }
      if ($total->getMetric()->getMachineName() == $metric_type) {
        return $total->getValue();
      }
      if ($metric_name && $total->getMetric()->getName() == $metric_name) {
        return $total->getValue();
      }
    }
    return NULL;
  }

}
