<?php

namespace Drupal\ghi_plans\ApiObjects\Measurements;

use Drupal\hpc_api\ApiObjects\ApiObjectInterface;

/**
 * Interface for API measurement objects.
 */
interface MeasurementInterface extends ApiObjectInterface {

  /**
   * Get a reporting period id for the measurement.
   *
   * @return int
   *   The id of the reporting period for a measurement.
   */
  public function getReportingPeriodId();

  /**
   * Get the comment for the measurement.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface|null
   *   The comment set for the measurement.
   */
  public function getComment();

}
