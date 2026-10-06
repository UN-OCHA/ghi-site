<?php

namespace Drupal\ghi_form_elements\Helpers;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;

/**
 * Provides editor feedback for map metric availability.
 */
final class MapMetricAvailabilityHelper {

  /**
   * Get a warning when a metric has no location-level data.
   */
  public static function getWarning(Attachment $attachment, string $metric, ?array $settings, array $availability, ?string $label = NULL, bool $required = FALSE): ?TranslatableMarkup {
    $available_metrics = $availability['base'];
    if ($attachment->isMeasurementField($metric)) {
      $reporting_period_id = $settings['monitoring_period'] ?? 'latest';
      $measurement_id = $attachment->getMeasurement($reporting_period_id)?->id();
      $available_metrics = $measurement_id ? ($availability['measurements'][$measurement_id] ?? []) : [];
    }
    if (in_array($metric, $available_metrics, TRUE)) {
      return NULL;
    }

    return new TranslatableMarkup('No location-level data is available for @metric. @consequence', [
      '@metric' => $label ?? ($attachment->getFields()[$metric] ?? $metric),
      '@consequence' => $required ? 'The map will not be displayed.' : 'This dataset will not appear on the map.',
    ]);
  }

}
