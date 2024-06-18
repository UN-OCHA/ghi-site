<?php

namespace Drupal\ghi_plans\ApiObjects\Measurements;

use Drupal\Core\Render\Markup;
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
    $totals = $measurement->measurementVersion->value->metrics->values->totals ?? [];
    $calculated_fields = $measurement->measurementVersion->value->metrics->calculatedFields ?? [];
    $processed = (object) [
      'reporting_period' => $measurement->planReportingPeriodId,
      'metrics' => $measurement->measurementVersion->value->metrics,
      'totals' => array_merge($totals, $calculated_fields),
      'disaggregated' => $measurement->measurementVersion->value->metrics->values->disaggregated ?? NULL,
      'comment' => !empty($measurement->isCommentPublic) ? ($measurement->measurementVersion->value->commentsMonitoring ?? NULL) : NULL,
    ];

    return $processed;
  }

  /**
   * {@inheritdoc}
   */
  public function getReportingPeriodId() {
    return $this->reporting_period;
  }

  /**
   * {@inheritdoc}
   */
  public function getDataPointValue($index) {
    return $this->totals[$index]?->value ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getComment() {
    return !empty($this->comment) ? Markup::create($this->comment) : NULL;
  }

}
