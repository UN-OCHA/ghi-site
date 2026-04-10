<?php

namespace Drupal\hpc_api\ApiObjects\Types;

use Drupal\hpc_api\ApiObjects\Type;
use Drupal\hpc_api\Helpers\StringHelper;

/**
 * Class for plan type objects.
 */
class PlanType extends Type {

  /**
   * Get the abbreaviation for the plan type.
   *
   * @return string
   *   The plan type abbreviation.
   */
  public function getAbbreviation() {
    return StringHelper::getAbbreviation($this->getName());
  }

}
