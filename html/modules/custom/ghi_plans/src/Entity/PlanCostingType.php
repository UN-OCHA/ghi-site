<?php

namespace Drupal\ghi_plans\Entity;

use Drupal\taxonomy\Entity\Term;

/**
 * Bundle class for plan costing type taxonomy terms.
 */
class PlanCostingType extends Term {

  /**
   * Get the abbreaviation for the plan type.
   *
   * @return string
   *   The plan type abbreviation.
   */
  public function getCode() {
    return $this->field_plan_costing_code?->value ?? NULL;
  }

  /**
   * Check if plans using this plan costing type have requirements.
   *
   * @return bool
   *   TRUE if plans using this costing type have their own requirements, FALSE
   *   otherwise.
   */
  public function isPlanRequirements() {
    return $this->getCode() == 3;
  }

  /**
   * Check if plans using this plan costing type have cluster requirements.
   *
   * @return bool
   *   TRUE if plans using this costing type get their requirements from the
   *   sum of the cluster requirements, FALSE otherwise.
   */
  public function isClusterRequirements() {
    return $this->getCode() !== NULL && in_array($this->getCode(), [1, 2]);
  }

}
