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
   * Maximum number of simultaneous summary requests.
   */
  private const PREFETCH_BATCH_SIZE = 50;

  /**
   * Get funding summaries for multiple plans.
   *
   * @param int[] $plan_ids
   *   The plan IDs to fetch.
   *
   * @return array<int, array<string, float>>
   *   Funding summaries keyed by plan ID.
   */
  public function getDataForPlans(array $plan_ids): array {
    $plan_ids = array_values(array_filter(array_unique(array_map('intval', $plan_ids))));
    $endpoint_urls = [];
    $cache_tags_by_url = [];
    foreach ($plan_ids as $plan_id) {
      $this->setPlaceholder('plan_id', $plan_id);
      $endpoint_url = $this->getFullEndpointUrl();
      $endpoint_urls[] = $endpoint_url;
      $cache_tags_by_url[$endpoint_url] = $this->getCacheTags();
    }
    foreach (array_chunk($endpoint_urls, self::PREFETCH_BATCH_SIZE) as $endpoint_url_batch) {
      $this->endpointQuery->queryPool($endpoint_url_batch, array_intersect_key($cache_tags_by_url, array_flip($endpoint_url_batch)));
    }

    $data = [];
    foreach ($plan_ids as $plan_id) {
      $data[$plan_id] = $this->getData(['plan_id' => $plan_id]);
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getData(array $placeholders = [], array $query_args = []) {
    $data = (array) parent::getData($placeholders, $query_args) + [
      'total_funding' => 0,
      'overall_funding' => 0,
    ];
    return [
      'total_funding' => $data['total_funding'],
      'overall_funding' => $data['overall_funding'],
    ];
  }

}
