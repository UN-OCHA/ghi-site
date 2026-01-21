<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

use Drupal\hpc_common\Helpers\CommonHelper;

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
    $totals = $this->getTotals();
    // @todo What to do if there are multiple requirement records?
    $requirements = reset($totals);
    return $requirements ? $requirements->getValue() : 0;
  }

  /**
   * Get the coverage for a plan based on the given funding.
   *
   * @param float $funding
   *   The funding to calculate the coverage against.
   *
   * @return float
   *   The coverage for a plan.
   */
  public function getCoverage(float $funding): float {
    return (float) CommonHelper::calculateRatio($funding, $this->getRequirements()) * 100;
  }

}
