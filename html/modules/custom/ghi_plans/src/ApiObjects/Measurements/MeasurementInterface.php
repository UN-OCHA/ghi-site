<?php

namespace Drupal\ghi_plans\ApiObjects\Measurements;

use Drupal\hpc_api\ApiObjects\ApiObjectInterface;

/**
 * Interface for API measurement objects.
 */
interface MeasurementInterface extends ApiObjectInterface {

  /**
   * Get a title for the attachment.
   *
   * @return int
   *   The id of the reporting period for a measurement.
   */
  public function getReportingPeriodId();

}
