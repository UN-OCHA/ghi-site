<?php

namespace Drupal\ghi_plans\ApiObjects;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Traits\PlanReportingPeriodTrait;
use Drupal\hpc_api\ApiObjects\ApiObjectBase;

/**
 * Abstraction class for API plan reporting period objects.
 */
class PlanReportingPeriod extends ApiObjectBase {

  use PlanReportingPeriodTrait;

  /**
   * The plan id.
   *
   * @var int
   */
  protected int $planId;

  /**
   * The period number.
   *
   * @var int
   */
  protected int $periodNumber;

  /**
   * Whether measurements have been generated.
   *
   * @var bool
   */
  protected bool $measurementsGenerated;

  /**
   * The start date.
   *
   * @var string|null
   */
  protected ?string $startDate;

  /**
   * The end date.
   *
   * @var string|null
   */
  protected ?string $endDate;

  const FORMAT_DATE = 'j M Y';
  const FORMAT_DATE_SHORT = 'j M';

  /**
   * Define the dimension items used in queries.
   */
  const GRAPHQL_ITEMS = [
    'Id',
    'StartDate',
    'EndDate',
    'PeriodNumber',
    'PlanId',
    'MeasurementsGenerated',
  ];

  /**
   * Define the properties used for storage lookups.
   */
  const LOOKUP_PROPERTIES = [
    'PlanId',
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(object $data) {
    parent::__construct($data);
    $this->planId = (int) $data->PlanId;
    $this->periodNumber = (int) $data->PeriodNumber;
    $this->measurementsGenerated = (bool) $data->MeasurementsGenerated;
    $this->startDate = $data->StartDate ?? NULL;
    $this->endDate = $data->EndDate ?? NULL;
  }

  /**
   * Get the plan id.
   *
   * @return int
   *   The plan ID if any can be found.
   */
  public function getPlanId(): int {
    return $this->planId;
  }

  /**
   * Get the start date.
   *
   * @return string|null
   *   The start date as a date string in the format "2024-07-01".
   */
  public function getStartDate(): ?string {
    return $this->startDate;
  }

  /**
   * Get the end date.
   *
   * @return string|null
   *   The end date as a date string in the format "2024-07-01".
   */
  public function getEndDate(): ?string {
    return $this->endDate;
  }

  /**
   * Get the period number.
   *
   * @return int
   *   The period number of the reporting period.
   */
  public function getPeriodNumber(): int {
    return $this->periodNumber;
  }

  /**
   * Check if the reporting period has been opened for data entry.
   *
   * @return bool
   *   TRUE if the it's open, FALSE otherwise.
   */
  public function isOpen(): bool {
    return $this->measurementsGenerated;
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
    if (!$this->getStartDate()) {
      return $this->t('n/a');
    }
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
    if (!$this->getEndDate()) {
      return $this->t('n/a');
    }
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
    $start_date = $this->getStartDate() ? $this->formatStartDate() : NULL;
    $end_date = $this->getEndDate() ? $this->formatEndDate() : NULL;
    if (!$start_date && $end_date) {
      return $this->t('until @date', [
        '@date' => $end_date,
      ]);
    }
    elseif ($start_date && !$end_date) {
      return $this->t('From @date', [
        '@date' => $start_date,
      ]);
    }
    elseif (!$start_date && !$end_date) {
      return $this->t('n/a');
    }
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
    $start = ($this->getPlanStartDate() ?? $this->getStartDate()) ? $this->getDateTimeObject($this->getPlanStartDate() ?? $this->getStartDate()) : NULL;
    $start_date = $start?->format($format ?? self::FORMAT_DATE) ?? NULL;
    $end_date = $this->getEndDate() ? $this->formatEndDate() : NULL;
    if (!$start_date && $end_date) {
      return $this->t('until @date', [
        '@date' => $end_date,
      ]);
    }
    elseif ($start_date && !$end_date) {
      return $this->t('From @date', [
        '@date' => $start_date,
      ]);
    }
    elseif (!$start_date && !$end_date) {
      return $this->t('n/a');
    }
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
