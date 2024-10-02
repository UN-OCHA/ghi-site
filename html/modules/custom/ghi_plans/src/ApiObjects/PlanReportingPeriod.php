<?php

namespace Drupal\ghi_plans\ApiObjects;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;

/**
 * Abstraction class for API plan reporting period objects.
 */
class PlanReportingPeriod extends BaseObject {

  const FORMAT_DATE = 'j M Y';
  const FORMAT_DATE_SHORT = 'j M';

  /**
   * Map the raw data.
   *
   * @return object
   *   An object with the mapped data.
   */
  protected function map() {
    $data = $this->getRawData();

    return (object) [
      'id' => $data->id,
      'plan_id' => $data->planId,
      'period_number' => $data->periodNumber,
      'start_date' => $data->startDate,
      'end_date' => $data->endDate,
    ];

  }

  /**
   * Get the plan id.
   *
   * @return int
   *   The plan ID if any can be found.
   */
  public function getPlanId() {
    return $this->plan_id;
  }

  /**
   * Get the start date.
   *
   * @return string
   *   The start date as a date string in the format "2024-07-01".
   */
  public function getStartDate() {
    return $this->start_date;
  }

  /**
   * Get the end date.
   *
   * @return string
   *   The end date as a date string in the format "2024-07-01".
   */
  public function getEndDate() {
    return $this->end_date;
  }

  /**
   * Get the period number.
   *
   * @return int
   *   The period number of the reporting period.
   */
  public function getPeriodNumber() {
    return $this->period_number;
  }

  /**
   * Get the plan start date.
   *
   * @return string
   *   The end date as a date string in the format "2024-07-01".
   */
  public function getPlanStartDate() {
    return $this->getPlanObject()?->getPlanStartDate() ?? NULL;
  }

  /**
   * Get the formatted start date.
   *
   * @param string $format
   *   An optional format for the date formatting.
   *
   * @return string
   *   The start date as a formatted string.
   */
  public function formatStartDate($format = NULL) {
    $date = $this->getDateTimeObject($this->getStartDate());
    return $date->format($format ?? self::FORMAT_DATE);
  }

  /**
   * Get the formatted end date.
   *
   * @param string $format
   *   An optional format for the date formatting.
   *
   * @return string
   *   The end date as a formatted string.
   */
  public function formatEndDate($format = NULL) {
    $date = $this->getDateTimeObject($this->getEndDate());
    return $date->format($format ?? self::FORMAT_DATE);
  }

  /**
   * Get the formatted date range.
   *
   * @return string
   *   The date range as a formatted string.
   */
  public function formatDateRange() {
    $start_date = $this->formatStartDate();
    $end_date = $this->formatEndDate();
    if ($this->formatStartDate('Y') == $this->formatEndDate('Y')) {
      $start_date = $this->formatStartDate(self::FORMAT_DATE_SHORT);
    }
    return $start_date . ' - ' . $end_date;
  }

  /**
   * Get the formatted cumulative date range.
   *
   * This uses the plan start date as the start date of the period.
   *
   * @return string
   *   The cumulative date range as a formatted string.
   */
  public function formatCumulativeDateRange() {
    $start = $this->getDateTimeObject($this->getPlanStartDate());
    $start_date = $start->format($format ?? self::FORMAT_DATE);
    $end_date = $this->formatEndDate();
    if ($start->format('Y') == $this->formatEndDate('Y')) {
      $start_date = $start->format(self::FORMAT_DATE_SHORT);
    }
    return $start_date . ' - ' . $end_date;
  }

  /**
   * Get a datetime object for the given date string.
   *
   * @param string $date
   *   A date as a string.
   *
   * @return \DateTime
   *   The datetime object for the given string date.
   */
  private function getDateTimeObject($date) {
    $timezone = $this->getTimezone();
    return new \DateTime($date, $timezone);
  }

  /**
   * Get the plan object for this attachment.
   *
   * @return \Drupal\ghi_plans\Entity\Plan|null
   *   The plan base object or NULL.
   */
  private function getPlanObject() {
    return BaseObjectHelper::getBaseObjectFromOriginalId($this->getPlanId(), 'plan');
  }

  /**
   * Get the timezone for date formatting.
   *
   * @return \DateTimeZone
   *   The timezone to use.
   */
  private function getTimezone() {
    // We want to handle all times as UTC, because that's what we get from the
    // API.
    return new \DateTimeZone('UTC');
  }

}
