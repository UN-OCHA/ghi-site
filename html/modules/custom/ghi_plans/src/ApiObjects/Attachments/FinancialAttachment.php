<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

/**
 * Abstraction for API financial attachment objects.
 */
class FinancialAttachment extends DataAttachment {

  /**
   * Get the requirements.
   *
   * @return float
   *   The requirements.
   */
  public function getRequirements() {
    $totals = $this->totals;
    $requirements = reset($totals);
    return $requirements ? $requirements->getValue() : 0;
  }

}
