<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

/**
 * Interface for API objects with disaggregated data.
 */
interface DisaggregatedDataInterface extends AttachmentInterface {

  /**
   * Build disaggregated data for the given facts.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Facts\AttachmentFact[]|\Drupal\ghi_plans\ApiObjects\Facts\MeasurementFact[] $facts
   *   An array of facts.
   *
   * @return object
   *   A disaggregated data object.
   */
  public function buildDisaggregatedData(array $facts): object;

}
