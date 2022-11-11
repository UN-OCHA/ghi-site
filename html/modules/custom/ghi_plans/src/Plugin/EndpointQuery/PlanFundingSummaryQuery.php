<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for plan funding summary.
 *
 * @EndpointQuery(
 *   id = "plan_funding_summary_query",
 *   label = @Translation("Plan funding summary query"),
 *   endpoint = {
 *     "public" = "fts/flow/plan/summary/{plan_id}",
 *     "version" = "v1"
 *   }
 * )
 */
class PlanFundingSummaryQuery extends EndpointQueryBase {

  /**
   * This holds the processed data.
   *
   * @var array
   */
  private $data = NULL;

  /**
   * {@inheritdoc}
   */
  public function getData(array $placeholders = [], array $query_args = []) {
    $data = (array) parent::getData($placeholders, $query_args);
    $data += [
      'total_funding' => 0,
      'overall_funding' => 0,
      'funding_progress' => 0,
      'unmet_requirements' => 0,
      'total_requirements' => 0,
      'original_requirements' => 0,
    ];
    return [
      'total_funding' => $data['total_funding'],
      'outside_funding' => $data['overall_funding'] - $data['total_funding'],
      'funding_coverage' => $data['funding_progress'],
      'funding_gap' => array_key_exists('unmet_requirements', $data) ? $data['unmet_requirements'] : $data['total_requirements'] - $data['total_funding'],
      'original_requirements' => $data['original_requirements'],
      'current_requirements' => $data['total_requirements'],
    ];
  }

  /**
   * Get a specific property from the current result set.
   *
   * @param string $property
   *   The property to retrieve.
   * @param mixed $default
   *   A default value.
   *
   * @return mixed
   *   The retrieved property or a default value.
   */
  public function get($property, $default) {
    if (empty($this->data)) {
      $this->data = $this->getData();
    }
    return !empty($this->data[$property]) ? $this->data[$property] : $default;
  }

}
