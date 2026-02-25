<?php

namespace Drupal\ghi_plans\ApiObjects\Measurements;

use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\hpc_api\ApiObjects\ApiObjectInterface;

/**
 * Interface for API measurement objects.
 */
interface MeasurementInterface extends ApiObjectInterface {

  /**
   * Extract the plan id from a measurement object.
   *
   * @return int|null
   *   The plan ID if any can be found.
   */
  public function getPlanId();

  /**
   * Get the source entity type.
   *
   * @return string|null
   *   The source entity type.
   */
  public function getSourceEntityType();

  /**
   * Get the source entity id.
   *
   * @return string|null
   *   The source entity id.
   */
  public function getSourceEntityId();

  /**
   * Get a reporting period id for the measurement.
   *
   * @return int
   *   The id of the reporting period for a measurement.
   */
  public function getReportingPeriodId();

  /**
   * Get the value for a data point.
   *
   * @return mixed|null
   *   The value for the data point with the given index
   */
  public function getDataPointValue($index);

  /**
   * Get the comment for the measurement.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface|null
   *   The comment set for the measurement.
   */
  public function getComment();

  /**
   * Get all values for this measurement.
   *
   * @return bool
   *   TRUE if there is data, FALSE otherwise.
   */
  public function getValues();

  /**
   * Get the totals from the measurement.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Facts\MeasurementFact[]
   *   An array of measurement fact objects.
   */
  public function getTotals(): array;

  /**
   * Get the disaggregated data from the attachment.
   *
   * @return object
   *   A disaggregated data object.
   */
  public function getDisaggregated(): object;

  /**
   * Get the prototype for an attachment.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype|null
   *   The attachment prototype object.
   */
  public function getPrototype(): ?AttachmentPrototype;

}
