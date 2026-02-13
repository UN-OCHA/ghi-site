<?php

namespace Drupal\ghi_plans\Traits;

use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;

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
      $disaggregation_id = $item->getCategoryIdentifier();

      $locations[$location_id] = $locations[$location_id] ?? (object) [
        'totals' => [],
        'categories' => [],
      ];
      $locations[$location_id]->totals[$metric->id()] = $locations[$location_id]->totals[$metric->id()] ?? 0;
      $locations[$location_id]->totals[$metric->id()] += $item->getValue();
      if ($disaggregation_id) {
        $locations[$location_id]->categories[$disaggregation_id] = $locations[$location_id]->categories[$disaggregation_id] ?? [];
        $locations[$location_id]->categories[$disaggregation_id][$metric->id()] = $item->getValue();
      }

      $metrics[$metric->id()] = $metrics[$metric->id()] ?? $metric;
      foreach ($item->getCategories() as $category) {
        $categories[$category->getUuid()] = $category;
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
   * BC layer to transform the disaggregated data for maps.
   *
   * @param object $data
   *   The disaggregated data received as build by
   *   DataAttachment::getDisaggregatedData().
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment
   *   The attachment object to which the data belongs.
   *
   * @return array
   *   The transformed data.
   */
  public function transformDisaggregatedMapData(object $data, DataAttachment $attachment): array {
    $transform = [];
    foreach (array_values($data->metrics) as $index => $metric) {
      /** @var \Drupal\hpc_api\ApiObjects\Types\MetricType $metric */
      $metric_locations = array_filter($data->locations, fn($item) => array_key_exists($metric->id(), $item->totals));
      $transform[$index] = [
        'metric' => (object) [
          'name' => (object) [
            'en' => $metric->getName(),
          ],
          'type' => $metric->getMachineName(),
          'value' => array_sum(array_map(fn ($item) => $item->totals[$metric->id()], $metric_locations)),
        ],
        'unit_type' => $attachment->getUnitType(),
        'is_measurement' => FALSE,
        'locations' => array_map(function ($item) use ($metric, $data) {
          $categories = [];
          foreach ($data->categories as $key => $category) {
            $categories[$category->getName()] = [
              'name' => $category->getName(),
              'data' => $item->categories[$key][$metric->id()] ?? 0,
            ];
          }
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
    }
    return $transform;
  }

}
