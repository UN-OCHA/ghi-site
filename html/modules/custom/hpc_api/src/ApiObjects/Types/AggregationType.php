<?php

namespace Drupal\hpc_api\ApiObjects\Types;

use Drupal\hpc_api\ApiObjects\Type;

/**
 * Class for aggregation type objects.
 */
class AggregationType extends Type {

  /**
   * Checks whether the type removes overlaps from higher-level totals.
   */
  public function isAdjusted(): bool {
    return strcasecmp($this->getName(), 'Adjusted') === 0;
  }

  /**
   * Checks whether the type represents the complete value of one plan.
   */
  public function isFull(): bool {
    return strcasecmp($this->getName(), 'Full') === 0;
  }

}
