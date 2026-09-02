<?php

namespace Drupal\ghi_plans\Traits;

use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\hpc_api\ApiObjects\Types\MetricType;
use Drupal\hpc_common\Helpers\ArrayHelper;

/**
 * Trait for working with disaggregated data.
 */
trait DisaggregatedDataTrait {

  /**
   * Build disaggregated data for the given facts.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Facts\AttachmentFact[]|\Drupal\ghi_plans\ApiObjects\Facts\MeasurementFact[] $facts
   *   An array of facts.
   *
   * @return object
   *   A disaggregated data object.
   */
  public function buildDisaggregatedData(array $facts): object {
    $locations = [];
    $metrics = [];
    $categories = [];
    foreach ($facts as $item) {
      $location_id = $item->getLocationId();
      $metric = $item->getMetric();
      $disaggregation_id = $item->getCombinedCategoryIdentifier();

      $locations[$location_id] = $locations[$location_id] ?? (object) [
        'totals' => [],
        'categories' => [],
      ];
      // Prepare the totals.
      $locations[$location_id]->totals[$metric->id()] = $locations[$location_id]->totals[$metric->id()] ?? 0;
      if (!$disaggregation_id) {
        // The actual total value for a location is a fact without any
        // categories, so the disaggregation id is empty.
        $locations[$location_id]->totals[$metric->id()] += $item->getValue();
      }
      if ($disaggregation_id) {
        // If the disaggregation id is not empty, we use the item value for the
        // category values.
        $locations[$location_id]->categories[$disaggregation_id] = $locations[$location_id]->categories[$disaggregation_id] ?? [];
        $locations[$location_id]->categories[$disaggregation_id][$metric->id()] = $item->getValue();
      }

      $metrics[$metric->id()] = $metrics[$metric->id()] ?? $metric;
      if ($disaggregation_id) {
        $categories[$disaggregation_id] = $item->getCombinedCategoryLabel();
      }
    }
    $disaggregated = (object) [
      'locations' => $locations,
      'metrics' => $metrics,
      'categories' => $categories,
    ];
    return $disaggregated;
  }

  /**
   * Filter the given data by the given metric type.
   *
   * @param object $data
   *   The disaggregated data to filter.
   * @param \Drupal\hpc_api\ApiObjects\Types\MetricType $metric_type
   *   The metric type to filter by.
   *
   * @return object
   *   The filtered data.
   */
  public function filterDisaggregatedData($data, MetricType $metric_type) {
    foreach ($data->locations as $location_id => &$location) {
      if (empty($location->totals[$metric_type->id()])) {
        unset($data->locations[$location_id]);
      }
      foreach ($location->categories as $category_id => $values) {
        if (empty($values[$metric_type->id()])) {
          unset($location->categories[$category_id]);
        }
        else {
          $keep_categories[$category_id] = TRUE;
        }
      }
    }
    $data->categories = empty($keep_categories) ? [] : array_intersect_key($data->categories, $keep_categories);
    return $data;
  }

  /**
   * BC layer to transform the disaggregated data for maps.
   *
   * @param object $data
   *   The disaggregated data received as build by
   *   Attachment::getDisaggregatedData().
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment
   *   The attachment object to which the data belongs.
   * @param bool $filter_empty_locations
   *   Whether to exclude empty locations.
   * @param bool $filter_empty_categories
   *   Whether to exclude empty categories.
   *
   * @return array
   *   The transformed data.
   */
  public function transformDisaggregatedMapData(object $data, Attachment $attachment, $filter_empty_locations = FALSE, $filter_empty_categories = FALSE): array {
    $transform = [];
    foreach (array_values($data->metrics) as $metric) {
      /** @var \Drupal\hpc_api\ApiObjects\Types\MetricType $metric */
      $index = $attachment->getPrototype()?->getOriginalIndexByMetricType($metric->getMachineName());
      if ($index === NULL) {
        continue;
      }
      $metric_locations = array_filter($data->locations, fn($item) => array_key_exists($metric->id(), $item->totals));
      $transform[$index] = [
        'metric' => (object) [
          'name' => (object) [
            'en' => $metric->getName(),
          ],
          'type' => $metric->getMachineName(),
          'value' => array_sum(array_map(fn ($item) => $item->totals[$metric->id()], $metric_locations)),
        ],
        'metric_object' => $metric,
        'unit_type' => $attachment->getUnitType(),
        'is_measurement' => FALSE,
        'locations' => array_map(function ($item) use ($metric, $data) {
          $categories = [];
          foreach ($data->categories as $key => $category_label) {
            $categories[$category_label] = [
              'name' => $category_label,
              'data' => $item->categories[$key][$metric->id()] ?? 0,
            ];
          }
          ArrayHelper::sortArrayByStringKey($categories, 'name');
          return [
            'id' => $item->location['id'],
            'name' => $item->location['name'],
            'total' => $item->totals[$metric->id()],
            'map_data' => $item->location + [
              'total' => $item->totals[$metric->id()],
              'object_id' => $item->location['id'],
              'location_id' => $item->location['id'],
              'location_name' => $item->location['name'],
            ],
            'categories' => $categories,
          ];
        }, $metric_locations),
      ];
      if ($filter_empty_locations) {
        $transform[$index]['locations'] = array_filter($transform[$index]['locations'], fn ($location) => !empty($location['total']));
        if (empty($transform[$index]['locations'])) {
          unset($transform[$index]);
        }
      }
    }
    ksort($transform);
    return $transform;
  }

}
