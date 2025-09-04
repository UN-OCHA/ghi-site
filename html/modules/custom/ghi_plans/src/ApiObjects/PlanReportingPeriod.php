<?php

namespace Drupal\ghi_plans\ApiObjects;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\ghi_base_objects\ApiObjects\BaseObject;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Traits\PlanReportingPeriodTrait;

/**
 * Abstraction class for API plan reporting period objects.
 */
class PlanReportingPeriod extends BaseObject {

  use PlanReportingPeriodTrait;

  const FORMAT_DATE = 'j M Y';
  const FORMAT_DATE_SHORT = 'j M';

  /**
   * Map the raw data.
   *
   * @return object
   *   An object with the mapped data.
   */
  protected function map(): object {
    $data = $this->getRawData();
    return (object) [
      'id' => $data->id,
      'plan_id' => $data->planId,
      'period_number' => $data->periodNumber,
      'measurements_generated' => $data->measurementsGenerated,
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
  public function getPlanId(): int {
    return $this->plan_id;
  }

  /**
   * Get the start date.
   *
   * @return string
   *   The start date as a date string in the format "2024-07-01".
   */
  public function getStartDate(): string {
    return $this->start_date;
  }

  /**
   * Get the end date.
   *
   * @return string
   *   The end date as a date string in the format "2024-07-01".
   */
  public function getEndDate(): string {
    return $this->end_date;
  }

  /**
   * Get the period number.
   *
   * @return int
   *   The period number of the reporting period.
   */
  public function getPeriodNumber(): int {
    return $this->period_number;
  }

  /**
   * Check if the reporting period has been opened for data entry.
   *
   * @return bool
   *   TRUE if the it's open, FALSE otherwise.
   */
  public function isOpen(): bool {
    return $this->measurements_generated;
  }

  /**
   * Check if the reporting period has been published.
   *
   * @return bool
   *   TRUE if the it's published, FALSE otherwise.
   */
  public function isPublished(): bool {
    $last_published_period = self::getLatestPublishedReportingPeriod($this->getPlanId());
    return is_int($last_published_period) && $this->id() <= $last_published_period;
  }

  /**
   * Get the plan start date.
   *
   * @return string|null
   *   The end date as a date string in the format "2024-07-01".
   */
  public function getPlanStartDate(): ?string {
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
  public function formatStartDate(?string $format = NULL): string {
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
  public function formatEndDate(?string $format = NULL): string {
    $date = $this->getDateTimeObject($this->getEndDate());
    return $date->format($format ?? self::FORMAT_DATE);
  }

  /**
   * Get the formatted date range.
   *
   * @return string
   *   The date range as a formatted string.
   */
  public function formatDateRange(): string {
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
  public function formatCumulativeDateRange(): string {
    $start = $this->getDateTimeObject($this->getPlanStartDate() ?? $this->getStartDate());
    $start_date = $start->format($format ?? self::FORMAT_DATE);
    $end_date = $this->formatEndDate();
    if ($start->format('Y') == $this->formatEndDate('Y')) {
      $start_date = $start->format(self::FORMAT_DATE_SHORT);
    }
    return $start_date . ' - ' . $end_date;
  }

  /**
   * Format a reporting period for output.
   *
   * @param string|\Drupal\Component\Render\MarkupInterface $format_string
   *   A formatting string.
   *
   * @return string
   *   A formatted string representing the reporting period.
   */
  public function format(?string $format_string = NULL): string {
    $format_string = $format_string ?? '#@period_number: @date_range';
    $args = [
      '@period_number' => $this->getPeriodNumber(),
      '@end_date' => $this->formatEndDate(),
      '@date_range' => $this->formatDateRange(),
      '@data_range_cumulative' => $this->formatCumulativeDateRange(),
    ];
    return (string) new FormattableMarkup($format_string, $args);
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
  private function getDateTimeObject(string $date): \DateTime {
    $timezone = $this->getTimezone();
    return new \DateTime($date, $timezone);
  }

  /**
   * Get the plan object for this attachment.
   *
   * @return \Drupal\ghi_plans\Entity\Plan|null
   *   The plan base object or NULL.
   */
  private function getPlanObject(): ?Plan {
    $base_object = BaseObjectHelper::getBaseObjectFromOriginalId($this->getPlanId(), 'plan');
    return $base_object instanceof Plan ? $base_object : NULL;
  }

  /**
   * Get the timezone for date formatting.
   *
   * @return \DateTimeZone
   *   The timezone to use.
   */
  private function getTimezone(): \DateTimeZone {
    // We want to handle all times as UTC, because that's what we get from the
    // API.
    return new \DateTimeZone('UTC');
  }

}
