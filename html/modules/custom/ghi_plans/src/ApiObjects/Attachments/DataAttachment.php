<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

use Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype;
use Drupal\ghi_plans\ApiObjects\Measurements\Measurement;
use Drupal\ghi_plans\Traits\PlanReportingPeriodTrait;
use Drupal\hpc_api\Helpers\ArrayHelper;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_api\Traits\SimpleCacheTrait;

/**
 * Abstraction for API data attachment objects.
 */
class DataAttachment extends AttachmentBase {

  use PlanReportingPeriodTrait;
  use SimpleCacheTrait;

  /**
   * Define the ID that is used for unit objects of type percentage.
   *
   * @todo Should be removed once HPC-5754 is done.
   */
  const UNIT_TYPE_ID_PERCENTAGE = 17;

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $attachment = $this->getRawData();
    $metrics = $this->getMetrics();
    $unit = property_exists($metrics, 'unit') && property_exists($metrics->unit, 'object') ? $metrics->unit->object : NULL;
    $prototype = $this->getPrototypeData();
    $period = $this->fetchReportingPeriodForAttachment();
    $measurement_fields = $metrics->measureFields ?? [];
    $references = explode('/', $attachment->composedReference);

    $processed = (object) [
      'id' => $attachment->id,
      'type' => strtolower($attachment->type),
      'source' => (object) [
        'entity_type' => $attachment->objectType ?? NULL,
        'entity_id' => $attachment->objectId ?? NULL,
        'plan_id' => $attachment->planId ?? NULL,
      ],
      'custom_id' => $attachment->attachmentVersion->value->customId ?? ($attachment->customReference ?? NULL),
      'custom_id_prefixed_refcode' => end($references),
      'composed_reference' => $attachment->composedReference,
      'description' => $attachment->attachmentVersion->value->description,
      'values' => $this->extractValues(),
      'prototype' => $prototype,
      'unit' => $unit ? (object) [
        'label' => $unit->label ?? NULL,
        'type' => $unit->id == self::UNIT_TYPE_ID_PERCENTAGE ? 'percentage' : 'amount',
        'group' => property_exists($unit, 'isGender') && $unit->isGender == 1 ? 'people' : 'amount',
      ] : NULL,
      'monitoring_period' => $period ?? NULL,
      'fields' => $prototype->fields,
      'measurement_fields' => $measurement_fields ? array_map(function ($field) {
        return $field->name->en;
      }, $measurement_fields) : [],
      'totals' => $attachment->attachmentVersion->value->metrics->values->totals,
      'disaggregated' => $attachment->attachmentVersion->value->metrics->values->disaggregated ?? NULL,
    ];

    // Cleanup the values.
    $processed->values = array_map(function ($value) {
      return $value === "" ? NULL : $value;
    }, $processed->values);

    return $processed;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->composed_reference;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * Get the source entity.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface
   *   The entity object.
   */
  public function getSourceEntity() {
    if (empty($this->source->entity_type) || $this->source->entity_type == 'plan' || empty($this->source->entity_id)) {
      return NULL;
    }
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\EntityQuery $entityQuery */
    $entityQuery = \Drupal::service('plugin.manager.endpoint_query_manager')->createInstance('entity_query');
    return $entityQuery->getEntity($this->source->entity_type, $this->source->entity_id);
  }

  /**
   * Get the metric fields.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getMetricFields() {
    return $this->fields;
  }

  /**
   * Get the fields that represent goal metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getGoalMetricFields() {
    $measurements = $this->measurement_fields;
    return array_filter($this->fields, function ($field) use ($measurements) {
      return !in_array($field, $measurements);
    });
  }

  /**
   * Get the fields that represent measurement metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getMeasurementMetricFields() {
    $measurements = $this->measurement_fields;
    return array_filter($this->fields, function ($field) use ($measurements) {
      return in_array($field, $measurements);
    });
  }

  /**
   * Get the type of unit for an attachment.
   */
  private function getUnitType() {
    return $this->unit ? $this->unit->type : NULL;
  }

  /**
   * Check if the given field label represens a measurement metric.
   *
   * @param string $field_label
   *   The field label.
   *
   * @return bool
   *   TRUE if the field is a measurement, FALSE otherwise.
   */
  public function isMeasurementField($field_label) {
    return in_array($field_label, $this->measurement_fields);
  }

  /**
   * Extract the plan id from an attachment object.
   *
   * @return int
   *   The plan ID if any can be found.
   */
  public function getPlanId() {
    $attachment_data = $this->getRawData();
    $plan_id = NULL;
    if (!empty($attachment_data->objectType) && is_string($attachment_data->objectType) && $attachment_data->objectType == 'plan') {
      $plan_id = $attachment_data->objectId;
    }
    elseif (!empty($attachment_data->planId)) {
      $plan_id = $attachment_data->planId;
    }
    elseif (!empty($attachment_data->attachmentPrototype->planId)) {
      $plan_id = $attachment_data->attachmentPrototype->planId;
    }
    return $plan_id;
  }

  /**
   * Get the disaggregated data for multiple reporting periods.
   *
   * @param array $reporting_period_ids
   *   The reporting periods to process.
   * @param bool $filter_empty_locations
   *   Whether to exlcude empty locations.
   * @param bool $filter_empty_categories
   *   Whether to exlcude empty categories.
   *
   * @return array
   *   An array of disaggregated data arrays per reporting period.
   */
  public function getDisaggregatedDataMultiple(array $reporting_period_ids = [], $filter_empty_locations = FALSE, $filter_empty_categories = FALSE) {
    $attachment_data = $this->getRawData();
    if (empty($attachment_data) || empty($reporting_period_ids)) {
      return NULL;
    }
    $plan_id = $this->getPlanId();
    if (!$plan_id) {
      return FALSE;
    }
    $reporting_periods = self::getReportingPeriods($plan_id, TRUE);
    if (empty($reporting_periods)) {
      return FALSE;
    }
    $map_data = [];
    foreach ($reporting_period_ids as $reporting_period_id) {
      if (!array_key_exists($reporting_period_id, $reporting_periods)) {
        continue;
      }
      $disaggregated_data = $this->getDisaggregatedData($reporting_period_id, $filter_empty_locations, $filter_empty_categories);
      if (empty($disaggregated_data)) {
        continue;
      }
      $map_data[$reporting_period_id] = [
        'reporting_period' => $reporting_period_id == 'latest' ? end($reporting_periods) : $reporting_periods[$reporting_period_id],
        'disaggregated_data' => $disaggregated_data,
      ];
    }
    return $map_data;
  }

  /**
   * Get the disaggregated data for a data attachment.
   *
   * @param int|string $reporting_period
   *   Either the id of a period, or the string latest.
   * @param bool $filter_empty_locations
   *   Whether to exlcude empty locations.
   * @param bool $filter_empty_categories
   *   Whether to exlcude empty categories.
   * @param bool $ignore_missing_location_ids
   *   Whether to ignore locations with missing ids.
   *
   * @return array
   *   A processed array of disaggregated data.
   */
  public function getDisaggregatedData($reporting_period = 'latest', $filter_empty_locations = FALSE, $filter_empty_categories = FALSE, $ignore_missing_location_ids = FALSE) {
    $this->assureDisaggregatedData();
    $attachment_data = $this->getRawData();

    // First check if we have already processed this data.
    $cache_key = $this->getCacheKey([
      'attachment_id' => $attachment_data->id,
      'reporting_period' => $reporting_period,
      'filter_empty_locations' => intval($filter_empty_locations),
      'filter_empty_categories' => intval($filter_empty_categories),
    ]);

    $cached_data = $this->cache($cache_key);
    if ($cached_data !== NULL) {
      return $cached_data;
    }

    // No, so we need to do it now. Extract the base metrics and base data.
    $base_metrics = $this->getBaseMetricTotals($reporting_period);
    $base_data = $this->getBaseData($reporting_period);

    // Remove the first row (full country totals, always empty for some reason
    // and not part of the locations array anyway).
    array_shift($base_data->dataMatrix);

    // Check if it's worth going on, no locations or no categories means we
    // won't have anything meaningful to display, so we bail out.
    $disaggregated_data = [];
    if (empty($base_data->locations) && empty($base_data->categories)) {
      return $this->cache($cache_key, FALSE);
    }

    $locations = $this->getLocations($base_data, $ignore_missing_location_ids);

    // Shortcut to the data matrix and the categories.
    $data_matrix = $base_data->dataMatrix;
    $categories = $base_data->categories;

    // Now $locations contains all locations that make up the data matrix,
    // containing map coordinates where available.
    // Now go through the properties and create a simplified version of the data
    // matrix.
    foreach ($base_metrics as $index => $property) {
      $disaggregated_data[$index] = [
        'metric' => $property,
        'unit_type' => $this->getUnitType(),
        'locations' => [],
        'category_totals' => [],
        'is_measurement' => $this->isMeasurementField($property->name->en),
      ];

      foreach ($locations as $location_index => $location) {
        if (empty($location->map_data)) {
          continue;
        }
        $disaggregated_data[$index]['locations'][$location_index] = (array) $location;
        $disaggregated_data[$index]['locations'][$location_index] += [
          'categories' => [],
          'total' => NULL,
        ];
        $location_data_matrix = $data_matrix[$location_index];

        $offset = 0;
        foreach ($categories as $category) {
          $disaggregated_data[$index]['locations'][$location_index]['categories'][$category->label] = [
            'name' => $category->name,
            'data' => array_key_exists($offset + $index, $location_data_matrix) ? $location_data_matrix[$offset + $index] : NULL,
          ];
          $offset += count($base_metrics);
        }

        $total = array_key_exists($offset + $index, $location_data_matrix) ? $location_data_matrix[$offset + $index] : NULL;
        $disaggregated_data[$index]['locations'][$location_index]['total'] = $total;
        $disaggregated_data[$index]['locations'][$location_index]['map_data']['total'] = $total;
      }

      foreach ($categories as $category_index => $category) {
        $category_index = count($base_metrics) * $category_index + $index;
        $first_row = reset($data_matrix);
        $category_total = array_key_exists($category_index, $first_row) ? $first_row[$category_index] : NULL;
        if (!empty($category_total)) {
          $disaggregated_data[$index]['category_totals'][$category->name] = $category_total;
        }
      }
    }

    if ($filter_empty_locations) {
      // Filter data points that are not relevant, which means all locations
      // where the categories have no data and where there is no total for the
      // location.
      foreach ($disaggregated_data as $metric_index => $metric_items) {
        foreach ($metric_items['locations'] as $location_index => $location_item) {
          $has_data = count(array_filter($location_item['categories'], function ($category) {
            return !empty($category['data']);
          })) > 0;
          if (!$has_data && empty($location_item['total'])) {
            unset($disaggregated_data[$metric_index]['locations'][$location_index]);
          }
        }
      }
    }

    if ($filter_empty_categories) {
      // Filter data points that are not relevant, which means all categories
      // which don't have data for any of the locations.
      foreach ($disaggregated_data as $metric_index => $metric_items) {
        if (empty($metric_items['locations'])) {
          // No locations, so there aren't any categories either.
          continue;
        }

        $empty_categories = [];
        foreach ($categories as $category) {
          $empty_categories[$category->label] = TRUE;
        }

        foreach ($metric_items['locations'] as $location_index => $location_item) {
          foreach ($location_item['categories'] as $category_key => $category) {
            if ($empty_categories[$category_key] === TRUE && !empty($category['data'])) {
              $empty_categories[$category_key] = FALSE;
            }
          }
        }

        foreach ($metric_items['locations'] as $location_index => $location_item) {
          foreach (array_keys(array_filter($empty_categories)) as $empty_category) {
            unset($disaggregated_data[$metric_index]['locations'][$location_index]['categories'][$empty_category]);
          }
        }
      }
    }

    return $this->cache($cache_key, $disaggregated_data);
  }

  /**
   * Assure that the disaggregated data for an attachment has been fetched.
   */
  private function assureDisaggregatedData() {
    $attachment_data = $this->getRawData();
    if (property_exists($attachment_data->attachmentVersion->value->metrics->values, 'disaggregated')) {
      // Nothing to do.
      return;
    }
    /** @var \Drupal\hpc_api\Query\EndpointQueryManager $endpoint_query_manager */
    $endpoint_query_manager = \Drupal::service('plugin.manager.endpoint_query_manager');
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentQuery $attachment_query */
    $attachment_query = $endpoint_query_manager->createInstance('attachment_query');
    $attachment_data = $attachment_query->getAttachmentWithDisaggregatedData($this->id);
    $this->setRawData($attachment_data);
    $this->updateMap();
  }

  /**
   * Retrieve the base metrics of an attachment.
   *
   * If measurements are available, those will be used, otherwhise the normal
   * metrics will be used.
   */
  private function getBaseMetricTotals($reporting_period = 'latest') {
    $measurement = $this->getMeasurementByReportingPeriod($reporting_period);
    if ($measurement && !empty($measurement->totals)) {
      $base_metrics = $measurement->totals;
    }
    else {
      $base_metrics = $this->totals;
    };
    return $base_metrics;
  }

  /**
   * Retrieve the base data of an attachment.
   *
   * If measurements are available, those will be used, otherwhise the normal
   * metrics data will be used.
   */
  private function getBaseData($reporting_period = 'latest') {
    $measurement = $this->getMeasurementByReportingPeriod($reporting_period);
    if ($measurement && !empty($measurement->disaggregated)) {
      $base_data = $measurement->disaggregated;
    }
    elseif (!empty($this->disaggregated)) {
      $base_data = $this->disaggregated;
    }
    else {
      return NULL;
    }
    return clone $base_data;
  }

  /**
   * Extract the country out of an array of locations.
   */
  private function getMainCountryFromLocations($locations) {
    // This gives either an array with a single location that is the main
    // country, or in case of some plans, like oPt 2017, which do not report the
    // relationship between the countries properly, this gives the original
    // locations array. The latter case is mitigated by the fact, that we assume
    // that the first element is the main country.
    $country_candidates = array_filter($locations, function ($location) {
      return empty($location->parent) && !empty($location->id);
    });
    if (empty($country_candidates)) {
      return NULL;
    }
    $country = reset($country_candidates);
    return $country;
  }

  /**
   * Get the main country from the plan id.
   *
   * @param int $plan_id
   *   The plan id for which to retrieve the country.
   *
   * @return object
   *   An object with 2 keys: 'id' and 'name'.
   */
  private function getMainCountryFromPlanId($plan_id) {
    /** @var \Drupal\hpc_api\Query\EndpointQueryManager $endpoint_query_manager */
    $endpoint_query_manager = \Drupal::service('plugin.manager.endpoint_query_manager');
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanLocationsQuery $plan_locations_query */
    $plan_locations_query = $endpoint_query_manager->createInstance('plan_locations_query');
    $plan_locations_query->setPlaceholder('plan_id', $plan_id);
    $country = $plan_locations_query->getCountry();
    return (object) [
      'id' => $country->id,
      'name' => $country->name,
    ];
  }

  /**
   * Get the locations for the current attachment.
   *
   * @param object $base_data
   *   The base data of the attachment.
   * @param bool $ignore_missing_location_ids
   *   Whether to ignore locations with missing ids.
   *
   * @return array
   *   An array of location objects.
   */
  private function getLocations($base_data, $ignore_missing_location_ids) {

    // We extract the country from the locations array.
    $country = $this->getMainCountryFromLocations($base_data->locations);
    if (!$country && !empty($this->getRawData()->planId)) {
      // The disaggregation data doesn't seem to include the main country in the
      // locations. Let's try to get it from the plan id.
      $country = $this->getMainCountryFromPlanId($this->getRawData()->planId);
    }

    // Then we remove the country from the locations array and create an array
    // of location spot candidates.
    $locations = array_filter($base_data->locations, function ($location) use ($country, $ignore_missing_location_ids) {
      return (!empty($location->id) || $ignore_missing_location_ids) && (($location->id ?? NULL) != $country->id);
    });

    /** @var \Drupal\hpc_api\Query\EndpointQueryManager $endpoint_query_manager */
    $endpoint_query_manager = \Drupal::service('plugin.manager.endpoint_query_manager');
    /** @var \Drupal\hpc_api\Plugin\EndpointQuery\LocationsQuery $locations_query */
    $locations_query = $endpoint_query_manager->createInstance('locations_query');

    // Then we get the coordinates for all locations that the API knows for this
    // country. The coordinates are keyed by the location id.
    /** @var \Drupal\hpc_api\ApiObjects\Location[] $location_coordinates */
    $location_coordinates = $country ? $locations_query->getCountryLocations($country) : [];

    foreach ($locations as $location_key => $location) {
      $locations[$location_key]->country_id = $country->id;
      if (empty($location->id)) {
        continue;
      }
      $coordinates = !empty($location_coordinates[$location->id]) ? $location_coordinates[$location->id] : NULL;
      if (empty($coordinates)) {
        continue;
      }
      $locations[$location_key]->map_data = $coordinates->toArray();
      $locations[$location_key]->map_data['total'] = 0;
    }
    return $locations;
  }

  /**
   * Extract the metric values from an attachment.
   *
   * @return array
   *   Array with values for each metric and measurement data point, according
   *   to the prototype.
   */
  protected function extractValues() {
    $prototype = $this->getPrototypeData();
    $metrics = $this->getMetrics();
    // Then get the measure fields.
    $measure_fields = $metrics->measureFields ?? NULL;
    // And merge metrics and measurements together.
    return array_pad(array_merge(
      array_map(function ($item) {
        return $item->value ?? NULL;
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
   * @return object
   *   A metric object.
   */
  protected function getMetrics() {
    $attachment = $this->getRawData();
    // Get the metrics from the attachment version by default.
    $metrics = $attachment->attachmentVersion->value->metrics;
    // If there are measurements, look at the most recent one and get the
    // metrics from there.
    $measurement = self::getCurrentMeasurement();
    if ($measurement) {
      $metrics = $measurement->metrics;
    }
    return $metrics;
  }

  /**
   * Get the all measurements.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Measurements\Measurement[]|null
   *   An array of measurement objects or NULL.
   */
  public function getMeasurements() {
    $attachment = $this->getRawData();
    if (!property_exists($attachment, 'measurements')) {
      /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\MeasurementQuery $measurements_query */
      $measurements_query = \Drupal::service('plugin.manager.endpoint_query_manager')->createInstance('measurement_query');
      $measurements = $measurements_query->getUnprocessedMeasurements($attachment->id);
      $attachment->measurements = $measurements;
      $this->setRawData($attachment);
      $this->updateMap();
    }
    else {
      $measurements = $attachment->measurements ?? [];
    }
    if (empty($measurements)) {
      return NULL;
    }
    ArrayHelper::sortObjectsByNumericProperty($measurements, 'planReportingPeriodId', EndpointQuery::SORT_DESC);
    return array_map(function ($measurement) {
      return new Measurement($measurement);
    }, $measurements);
  }

  /**
   * Get the current measurement.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Measurements\Measurement|null
   *   The measurement object or NULL.
   */
  protected function getCurrentMeasurement() {
    $measurements = $this->getMeasurements();
    return $measurements ? reset($measurements) : NULL;
  }

  /**
   * Get a specific measurement by the reporting period.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Measurements\Measurement|null
   *   The measurement object or NULL.
   */
  protected function getMeasurementByReportingPeriod($reporting_period) {
    if ($reporting_period == 'latest') {
      return $this->getCurrentMeasurement();
    }
    if ($reporting_period) {
      $measurements = $this->getMeasurements() ?? [];
      foreach ($measurements as $measurement) {
        if ($measurement->getReportingPeriodId() == $reporting_period) {
          return $measurement;
        }
      }
    }
    return NULL;
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
   * @return object
   *   An object with at least the relevant parts of an attachment prototype.
   *
   * @throws \Exception
   *   If the prototype cannot be inferred.
   */
  protected function getPrototypeData() {
    $attachment = $this->getRawData();
    if (property_exists($attachment, 'attachmentPrototype')) {
      $prototype = new AttachmentPrototype($attachment->attachmentPrototype);
    }
    else {
      $prototype = self::fetchPrototypeForAttachment($attachment);
    }

    if (!$prototype) {
      throw new \Exception(sprintf('Failed to extract prototype for attachment %s', $attachment->id));
    }

    return $prototype;
  }

  /**
   * Fetch prototype data from the API.
   *
   * @param object $attachment
   *   The attachment object from the API.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype
   *   An attachment prototype object.
   */
  protected static function fetchPrototypeForAttachment($attachment) {
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentPrototypeQuery $query */
    $query_handler = \Drupal::service('plugin.manager.endpoint_query_manager')->createInstance('attachment_prototype_query');
    return $query_handler->getPrototypeByPlanAndId($attachment->planId, $attachment->attachmentPrototypeId);
  }

  /**
   * Fetch the reporting period for the given attachment.
   *
   * @return object|null
   *   A reporting period object or NULL.
   */
  protected function fetchReportingPeriodForAttachment() {
    $attachment = $this->getRawData();
    if (!property_exists($attachment, 'planId') || !$attachment->planId) {
      return NULL;
    }
    $measurement = $this->getCurrentMeasurement();
    if (!$measurement) {
      return NULL;
    }
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanReportingPeriodsQuery $planReportingPeriodsQuery */
    $planReportingPeriodsQuery = \Drupal::service('plugin.manager.endpoint_query_manager')->createInstance('plan_reporting_periods_query');
    $planReportingPeriodsQuery->setPlaceholder('plan_id', $attachment->planId);
    return $planReportingPeriodsQuery->getReportingPeriod($measurement->getReportingPeriodId());
  }

}
