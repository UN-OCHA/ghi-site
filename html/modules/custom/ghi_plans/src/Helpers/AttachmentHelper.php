<?php

namespace Drupal\ghi_plans\Helpers;

use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;

/**
 * Helper class for mapping attachment objects.
 */
class AttachmentHelper {

  /**
   * Process an array of attachments.
   *
   * @param array $attachments
   *   The input attachments to process.
   *
   * @return array
   *   An array of processed attachments.
   */
  public static function processAttachments(array $attachments) {
    $processed = [];
    foreach ($attachments as $attachment) {
      $processed[$attachment->id] = self::processAttachment($attachment);
    }
    return $processed;
  }

  /**
   * Process a single attachment.
   *
   * @param object $attachment
   *   The input attachment to process.
   *
   * @return object
   *   A single processed attachment.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidTypeException
   *   For unsupported attachment types, an Exception is thrown.
   */
  public static function processAttachment($attachment) {
    switch (strtolower($attachment->type)) {
      case 'caseload':
      case 'indicator':
        return self::processDataAttachment($attachment);

      case 'filewebcontent':
        return self::processFileAttachment($attachment);

      case 'textwebcontent':
        return self::processTextAttachment($attachment);

      default:
        throw new InvalidTypeException(sprintf('Unknown attachment type: %s', $attachment->type));
    }
  }

  /**
   * Process a text attachment.
   *
   * @param object $attachment
   *   The input attachment.
   *
   * @return object
   *   The processed text attachment.
   */
  private static function processTextAttachment($attachment) {
    return (object) [
      'id' => $attachment->id,
      'type' => strtolower($attachment->type),
      'title' => $attachment->attachmentVersion->value->name,
      'content' => html_entity_decode($attachment->attachmentVersion->value->content ?? ''),
    ];
  }

  /**
   * Process a file attachment.
   *
   * @param object $attachment
   *   The input attachment.
   *
   * @return object
   *   The processed file attachment.
   */
  private static function processFileAttachment($attachment) {
    return (object) [
      'id' => $attachment->id,
      'type' => strtolower($attachment->type),
      'url' => $attachment->attachmentVersion->value->file->url,
      'title' => $attachment->attachmentVersion->value->file->title ?? '',
      'file_name' => $attachment->attachmentVersion->value->name ?? '',
    ];
  }

  /**
   * Process a data attachment.
   *
   * @param object $attachment
   *   The input attachment.
   *
   * @return object
   *   The processed data attachment.
   */
  private static function processDataAttachment($attachment) {
    $metrics = $attachment->attachmentVersion->value->metrics;
    $unit = property_exists($metrics, 'unit') ? $metrics->unit : NULL;
    $prototype = self::getPrototypeData($attachment);
    $period = self::fetchReportingPeriodForAttachment($attachment);
    $metrics = self::getMetrics($attachment);
    $measurement_fields = $metrics->measureFields ?? [];

    $processed = (object) [
      'id' => $attachment->id,
      'type' => strtolower($attachment->type),
      'source' => (object) [
        'entity_type' => $attachment->objectType ?? NULL,
        'entity_id' => $attachment->objectId ?? NULL,
        'plan_id' => $attachment->planId ?? NULL,
      ],
      'composed_reference' => $attachment->composedReference,
      'description' => $attachment->attachmentVersion->value->description ?? NULL,
      'values' => self::extractValues($attachment),
      'prototype' => $prototype,
      'unit' => $unit ? (object) [
        'label' => $unit->label ?? NULL,
        'type' => $unit->type ?? NULL,
        'group' => property_exists($unit, 'isGender') && $unit->isGender == 1 ? 'people' : 'amount',
      ] : NULL,
      'monitoring_period' => $period ?? NULL,
      'fields' => $prototype->fields,
      'measurement_fields' => $measurement_fields ? array_map(function ($field) {
        return $field->name->en;
      }, $measurement_fields) : [],
    ];

    // Cleanup the values.
    $processed->values = array_map(function ($value) {
      return $value === "" ? NULL : $value;
    }, $processed->values);

    return $processed;
  }

  /**
   * Extract the metric values from an attachment.
   *
   * @param object $attachment
   *   The attachment from which to extract values.
   *
   * @return array
   *   Array with values for each metric and measurement data point, according
   *   to the prototype.
   */
  private static function extractValues($attachment) {
    $prototype = self::getPrototypeData($attachment);
    $metrics = self::getMetrics($attachment);
    // Then get the measure fields.
    $measure_fields = $metrics->measureFields ?? NULL;
    // And merge metrics and measurements together.
    return array_pad(array_merge(
      array_map(function ($item) {
        return $item->value;
      }, $metrics->values->totals),
      $measure_fields ? array_map(function ($item) {
        return $item->value ?? NULL;
      }, $measure_fields) : []
    ), count($prototype->fields), NULL);
  }

  /**
   * Get the metrics from the given attachment.
   *
   * This fetches either the metrics from the attachment version, or from a
   * measurement if a published one is already present.
   *
   * @param object $attachment
   *   The attachment from which to extract metrics.
   *
   * @return object
   *   A metric object.
   */
  private static function getMetrics($attachment) {
    // Get the metrics from the attachment version by default.
    $metrics = $attachment->attachmentVersion->value->metrics;
    // If there are measurements, look at the most recent one and get the
    // metrics from there.
    $measurement = self::getCurrentMeasurement($attachment);
    if ($measurement) {
      $metrics = $measurement->measurementVersion->value->metrics;
    }
    return $metrics;
  }

  /**
   * Get the current measurement.
   *
   * @param object $attachment
   *   The attachment from which to get the current measurement.
   *
   * @return object|null
   *   The measurement object or NULL.
   */
  private static function getCurrentMeasurement($attachment) {
    if (!property_exists($attachment, 'measurements')) {
      /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\MeasurementQuery $measurements_query */
      $measurements_query = \Drupal::service('plugin.manager.endpoint_query_manager')->createInstance('measurement_query');
      $measurements = $measurements_query->getUnprocessedMeasurements($attachment->id);
    }
    else {
      $measurements = $attachment->measurements ?? [];
    }
    if (empty($measurements)) {
      return NULL;
    }
    ArrayHelper::sortObjectsByNumericProperty($measurements, 'planReportingPeriodId', EndpointQuery::SORT_DESC);
    return reset($measurements);
  }

  /**
   * Extract prototype information from an attachment.
   *
   * Not all endpoints include the prototype in the response, which is why we
   * provide a work around to infer the prototype from the attachment object
   * itself. This does not contain all the prototype information. Calling
   * classes should assure that they use the correct endpoint if full prototype
   * data is required.
   *
   * @param object $attachment
   *   The attachment object as returned from the API.
   *
   * @return object
   *   An object with at least the relevant parts of an attachment prototype.
   *
   * @throws \Exception
   *   If the prototype cannot be inferred.
   */
  private static function getPrototypeData($attachment) {
    if (property_exists($attachment, 'attachmentPrototype')) {
      $prototype = $attachment->attachmentPrototype;
    }
    else {
      $prototype = self::fetchPrototypeForAttachment($attachment);
    }

    if (!$prototype) {
      throw new \Exception(sprintf('Failed to extract prototype for attachment %s', $attachment->id));
    }

    return (object) [
      'id' => $prototype->id,
      'name' => $prototype->value->name->en,
      'ref_code' => $prototype->refCode,
      'type' => strtolower($prototype->type),
      'fields' => array_merge(
        array_map(function ($item) {
          return $item->name->en;
        }, $prototype->value->metrics),
        array_map(function ($item) {
          return $item->name->en;
        }, $prototype->value->measureFields)
      ),
    ];
  }

  /**
   * Fetch prototype data from the API.
   *
   * @param object $attachment
   *   The attachment object from the API.
   *
   * @return object
   *   An attachment prototype object.
   */
  private static function fetchPrototypeForAttachment($attachment) {
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentPrototypeQuery $query */
    $query_handler = \Drupal::service('plugin.manager.endpoint_query_manager')->createInstance('attachment_prototype_query');
    return $query_handler->getPrototypeByPlanAndId($attachment->planId, $attachment->attachmentPrototypeId);
  }

  /**
   * Fetch the reporting period for the given attachment.
   *
   * @param object $attachment
   *   The attachment object from the API.
   *
   * @return object|null
   *   A reporting period object or NULL.
   */
  private static function fetchReportingPeriodForAttachment($attachment) {
    if (!property_exists($attachment, 'planId') || !$attachment->planId) {
      return NULL;
    }
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanReportingPeriodsQuery $planReportingPeriodsQuery */
    $planReportingPeriodsQuery = \Drupal::service('plugin.manager.endpoint_query_manager')->createInstance('plan_reporting_periods_query');
    $planReportingPeriodsQuery->setPlaceholder('plan_id', $attachment->planId);
    $measurement = self::getCurrentMeasurement($attachment);
    if (!$measurement) {
      return NULL;
    }
    return $planReportingPeriodsQuery->getReportingPeriod($measurement->planReportingPeriodId);
  }

}
