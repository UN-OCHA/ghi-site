<?php

namespace Drupal\ghi_plans\ApiObjects\Measurements;

use Drupal\hpc_api\ApiObjects\ApiObjectBase;

/**
 * Abstraction class for API measurement objects.
 */
class Measurement extends ApiObjectBase implements MeasurementInterface {

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $measurement = $this->getRawData();
    $processed = (object) [
      'reporting_period' => $measurement->planReportingPeriodId,
      'metrics' => $measurement->measurementVersion->value->metrics,
      'totals' => $measurement->measurementVersion->value->metrics->values->totals,
      'disaggregated' => $measurement->measurementVersion->value->metrics->values->disaggregated ?? NULL,
    ];

    return $processed;
  }

  /**
   * Get the reporting period id for a measurement.
   *
   * @return int
   *   The id of the reporting period for a measurement.
   */
  public function getReportingPeriodId() {
    return $this->reporting_period;
  }

}
