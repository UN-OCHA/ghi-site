<?php

namespace Drupal\ghi_plans\Entity;

use Drupal\hpc_common\Helpers\StringHelper;
use Drupal\taxonomy\Entity\Term;

/**
 * Bundle class for plan type taxonomy terms.
 */
class PlanType extends Term {

  /**
   * Get the abbreaviation for the plan type.
   *
   * @return string
   *   The plan type abbreviation.
   */
  public function getAbbreviation() {
    return $this->field_abbreviation?->value ?: StringHelper::getAbbreviation($this->label());
  }

  /**
   * Get the group key for the plan type.
   *
   * @return string
   *   The plan type group key.
   */
  public function getGroupKey() {
    return $this->field_group_key?->value ?: $this->tid->value;
  }

}
