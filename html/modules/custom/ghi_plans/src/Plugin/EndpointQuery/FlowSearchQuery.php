<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\hpc_api\Query\EndpointQueryBase;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\hpc_common\Helpers\CommonHelper;

/**
 * Provides a query plugin for flow search.
 *
 * @EndpointQuery(
 *   id = "flow_search_query",
 *   label = @Translation("Flow search query"),
 *   endpoint = {
 *     "public" = "fts/flow/custom-search",
 *     "version" = "v1"
 *   }
 * )
 */
class FlowSearchQuery extends EndpointQueryBase {

  /**
   * Search with arguments.
   *
   * @param array $arguments
   *   The arguments for the query.
   *
   * @return object
   *   The result set.
   */
  public function search(array $arguments) {
    $data = $this->getData([], $arguments);
    if (empty($data)) {
      return NULL;
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getClusterSummaryData() {
    $placeholders = $this->getPlaceholders();
    $query_args = [
      'planId' => $placeholders['plan_id'],
      'groupBy' => 'cluster',
      'report' => 3,
    ];
    $cache_key = $this->getCacheKey(['type' => 'cluster_summary'] + $this->getPlaceholders() + $query_args);
    $runtime_cache = &drupal_static(__METHOD__, []);
    if (array_key_exists($cache_key, $runtime_cache)) {
      return $runtime_cache[$cache_key];
    }

    // This method intentionally keeps endpoint-specific validation out of the
    // generic EndpointQuery transport/cache layer. The raw legacy API response
    // can be cached by hpc_api, but this plugin owns the domain meaning of the
    // response. We therefore keep a second, processed "last good" summary cache
    // here and only promote endpoint data into it when the cluster funding
    // breakdown is complete enough for plan funding displays.
    $previous_summary_data = $this->getCachedClusterSummaryData($cache_key, TRUE);
    $summary_data = $this->getCachedClusterSummaryData($cache_key);
    if ($summary_data) {
      $runtime_cache[$cache_key] = $summary_data;
      return $summary_data;
    }

    // If the normal cache entry exists but does not pass the processed summary
    // shape check, remove it unless there is an older invalidated entry that is
    // still usable as "last good" data. This prevents malformed processed data
    // from being tried over and over again while preserving the stale fallback.
    if (!$previous_summary_data && $this->getCache($cache_key) !== NULL) {
      $this->cache($cache_key, NULL, TRUE);
    }

    // First ask EndpointQuery for data normally. That allows fresh/stale raw
    // remote-cache responses to be used and keeps the hot path cheap.
    $candidate = $this->buildClusterSummaryCandidate(parent::getData($placeholders, $query_args));

    // When the normal candidate is interpretable but incomplete, do one direct
    // uncached endpoint read. This is the small, local repair for the observed
    // failure mode: a raw cached FTS response may be syntactically valid, but
    // semantically incomplete for cluster funding. Retrying without reading the
    // raw cache gives a healthy endpoint a chance to heal the processed summary
    // immediately, without adding endpoint-specific rules to EndpointQuery. We
    // deliberately do not retry total failures here, because normal HTTP/error
    // fallback is already handled by EndpointQuery and should not be doubled.
    if ($candidate && !$candidate->is_complete) {
      $retry_candidate = $this->buildClusterSummaryCandidate($this->getUncachedData($placeholders, $query_args));
      if ($retry_candidate?->is_complete) {
        $candidate = $retry_candidate;
      }
    }

    // If neither the cached/raw path nor the direct retry yielded any usable
    // summary object, fall back to the previous processed summary if possible.
    // With no previous summary, callers receive NULL rather than invented zero
    // funding values.
    if (!$candidate) {
      $runtime_cache[$cache_key] = $previous_summary_data;
      return $runtime_cache[$cache_key];
    }

    // Only complete candidates are promoted into the processed cache.
    // Incomplete candidates can still be returned when no previous summary
    // exists, because their missing funding cells remain NULL and are safer
    // than the historical behavior that turned omitted endpoint rows into zero
    // funding.
    if ($candidate->is_complete) {
      $this->setCache($cache_key, $candidate->summary);
    }
    $runtime_cache[$cache_key] = $candidate->is_complete || !$previous_summary_data
      ? $candidate->summary
      : $previous_summary_data;
    return $runtime_cache[$cache_key];
  }

  /**
   * Build a processed cluster summary candidate from raw endpoint data.
   *
   * The legacy FTS endpoint can return a HTTP 200 response with a valid wrapper
   * and still omit cluster-level funding rows. This helper transforms raw data
   * into the compact shape used by plan blocks and reports whether that compact
   * shape is complete. Keeping the completeness check beside the transformation
   * avoids a second endpoint-specific validator layer and makes it explicit
   * that only this plugin understands the response semantics.
   *
   * @param mixed $data
   *   The raw endpoint data returned by EndpointQuery.
   *
   * @return object|null
   *   An object with `summary` and `is_complete` properties, or NULL when the
   *   endpoint payload cannot be interpreted as cluster summary data at all.
   */
  private function buildClusterSummaryCandidate(mixed $data): ?object {
    if ($data === FALSE || $data === NULL || !is_object($data)) {
      return NULL;
    }

    $funding_totals = $data->report3->fundingTotals->objects[0] ?? NULL;
    if (!$funding_totals || !property_exists($funding_totals, 'objectsBreakdown') || !property_exists($funding_totals, 'totalBreakdown')) {
      return NULL;
    }
    $clusters = $funding_totals->objectsBreakdown ?? [];
    $totals = $funding_totals->totalBreakdown ?? NULL;
    if (!is_array($clusters) || empty($totals)) {
      return NULL;
    }

    $reported_cluster_ids = [];
    $summary_clusters = [];
    foreach ($clusters as $cluster) {
      $cluster_id = $cluster->id ?? NULL;
      $total_funding = property_exists($cluster, 'totalFunding') ? $cluster->totalFunding : NULL;

      // A reported value of 0 is valid and must be preserved. The incomplete
      // case we are guarding against is a missing row/property for a cluster
      // that requirements data says should have an explicit funding row.
      if ($cluster_id && $total_funding !== NULL) {
        $reported_cluster_ids[] = (int) $cluster_id;
      }
      $summary_clusters[] = (object) [
        // Id is not set for "Not specified clusters".
        'id' => $cluster_id,
        'name' => $cluster->name ?? NULL,
        'total_funding' => $total_funding,
      ];
    }

    $summary_data = (object) [
      'clusters' => $summary_clusters,
      'expected_cluster_ids' => $this->getExpectedClusterIds($data),
      'totals' => (object) [
        'sum' => $totals?->objectsSum ?? 0,
        'overlap' => $totals?->overlapCorrection ?? 0,
        'shared' => $totals?->sharedFunding ?? 0,
        'total_funding' => $totals?->totalFunding ?? 0,
      ],
    ];
    $summary_is_complete = empty(array_diff(
      $summary_data->expected_cluster_ids,
      array_unique($reported_cluster_ids)
    ));
    return (object) [
      'summary' => $summary_data,
      'is_complete' => $summary_is_complete,
    ];
  }

  /**
   * Fetch data once without reading EndpointQuery's raw response cache.
   *
   * This is deliberately a narrow repair path. EndpointQuery remains the
   * generic transport service. For this domain-specific summary we may need to
   * bypass a syntactically valid raw cached response that is semantically
   * incomplete. The cache flag is restored immediately so the cloned endpoint
   * query behaves normally for all later calls.
   *
   * @param array $placeholders
   *   Endpoint placeholders.
   * @param array $query_args
   *   Endpoint query arguments.
   *
   * @return mixed
   *   The raw endpoint data.
   */
  private function getUncachedData(array $placeholders, array $query_args): mixed {
    $use_cache = $this->endpointQuery->useCache();
    $this->endpointQuery->setUseCache(FALSE);
    try {
      return parent::getData($placeholders, $query_args);
    }
    finally {
      $this->endpointQuery->setUseCache($use_cache);
    }
  }

  /**
   * Get processed cluster summary data from the existing cache entry.
   *
   * @param string $cache_key
   *   The cache key.
   * @param bool $allow_invalid
   *   Whether to accept expired or invalidated cache data.
   *
   * @return object|null
   *   The cached summary data, or NULL.
   */
  protected function getCachedClusterSummaryData(string $cache_key, bool $allow_invalid = FALSE): ?object {
    if (!$allow_invalid) {
      $summary_data = $this->getCache($cache_key);
    }
    else {
      $cache = \Drupal::cache()->get($cache_key, TRUE);
      $summary_data = $cache ? $cache->data : NULL;
    }
    return $this->isValidClusterSummaryData($summary_data) ? $summary_data : NULL;
  }

  /**
   * Check whether processed cluster summary data has the expected structure.
   *
   * @param mixed $summary_data
   *   The processed cluster summary data.
   *
   * @return bool
   *   TRUE if the summary can be used, FALSE otherwise.
   */
  private function isValidClusterSummaryData(mixed $summary_data): bool {
    return is_object($summary_data) && property_exists($summary_data, 'clusters') && is_array($summary_data->clusters) && property_exists($summary_data, 'totals') && is_object($summary_data->totals);
  }

  /**
   * Get cluster ids that are expected to have explicit endpoint funding rows.
   *
   * @param object $data
   *   The raw endpoint data.
   *
   * @return int[]
   *   Cluster ids from endpoint requirements data.
   */
  private function getExpectedClusterIds(object $data): array {
    $objects = $data->requirements->objects ?? [];
    if (!is_array($objects)) {
      return [];
    }
    $ids = [];
    foreach ($objects as $object) {
      $object_type = strtolower((string) ($object->objectType ?? ''));
      if ($object_type && !in_array($object_type, ['cluster', 'governing entity', 'governingentity'], TRUE)) {
        continue;
      }
      if (!empty($object->id)) {
        $ids[] = (int) $object->id;
      }
    }
    return array_values(array_unique($ids));
  }

  /**
   * Get a property from a cluster object.
   *
   * @param object $cluster
   *   The cluster for which to retrieve the property.
   * @param string $property
   *   The property to retrieve. See self::getData().
   * @param mixed $default
   *   A default value to return if the property is not set.
   *
   * @return mixed
   *   The value for the property on the cluster, or the default value.
   */
  private function getClusterProperty($cluster, $property, $default = NULL) {
    if (!$cluster || !is_object($cluster)) {
      return $default;
    }
    return property_exists($cluster, $property) ? $cluster->$property : $default;
  }

  /**
   * Get a property from one of the clusters.
   *
   * @param int $cluster_id
   *   The cluster id for which to retrieve the property.
   * @param string $property
   *   The property to retrieve. See self::getData().
   * @param mixed $default
   *   A default value to return if the property is not set.
   *
   * @return mixed
   *   The value for tha property on the cluster, or the default value.
   */
  public function getClusterPropertyById($cluster_id, $property, $default = NULL) {
    $data = $this->getClusterSummaryData();
    if (empty($data) || empty($data->clusters)) {
      return $default;
    }
    $cluster = ArrayHelper::findFirstItemByProperties($data->clusters, ['id' => $cluster_id]);
    return $this->getClusterProperty($cluster, $property, $default);
  }

  /**
   * Get the total funding for a cluster.
   *
   * @param int $cluster_id
   *   The cluster id.
   * @param float|null $default
   *   Optional default value.
   *
   * @return float|null
   *   The total funding for the given cluster id, or NULL if unavailable.
   */
  public function getClusterTotalFunding($cluster_id, $default = NULL): ?float {
    $summary_data = $this->getClusterSummaryData();
    if (!is_object($summary_data)) {
      return NULL;
    }
    $funding = $this->getClusterPropertyById($cluster_id, 'total_funding', $default);
    return $funding !== NULL ? (float) $funding : NULL;
  }

  /**
   * Get the funding gap for a cluster and the given requirements.
   *
   * @param int $cluster_id
   *   The cluster id.
   * @param float|null $requirements
   *   The requirements to compare the funding against.
   *
   * @return float|null
   *   The funding gap for the given cluster id, or NULL if unavailable.
   */
  public function getClusterFundingGap(int $cluster_id, ?float $requirements): ?float {
    $funding = $this->getClusterTotalFunding($cluster_id);
    if ($funding === NULL || $requirements === NULL) {
      return NULL;
    }
    return $requirements - $funding;
  }

  /**
   * Get the funding coverage for a cluster and the given requirements.
   *
   * @param int $cluster_id
   *   The cluster id.
   * @param float|null $requirements
   *   The requirements to compare the funding against.
   *
   * @return float|null
   *   The funding coverage for the given cluster id, or NULL if unavailable.
   */
  public function getClusterFundingCoverage($cluster_id, ?float $requirements = 0): ?float {
    $funding = $this->getClusterTotalFunding($cluster_id);
    if ($funding === NULL || $requirements === NULL) {
      return NULL;
    }
    return (float) CommonHelper::calculateRatio($funding, $requirements, 4) * 100;
  }

  /**
   * Get the not specified cluster from the result set.
   *
   * This should be the only one with a missing or empty id property.
   *
   * @return object|null
   *   The cluster object or NULL if none can be found.
   */
  public function getNotSpecifiedCluster() {
    $data = $this->getClusterSummaryData();
    if (empty($data) || empty($data->clusters)) {
      return NULL;
    }
    foreach ($data->clusters as $cluster) {
      if (!property_exists($cluster, 'id') || empty($cluster->id)) {
        return $cluster;
      }
    }
    return NULL;
  }

  /**
   * Checks if the current result set has shared funding.
   *
   * @return bool
   *   TRUE if the data contains shared funding, FALSE if it doesn't.
   */
  public function hasSharedClusterFunding() {
    $data = $this->getClusterSummaryData();
    return !empty($data->totals->shared);
  }

  /**
   * Checks if the current result set has shared funding.
   *
   * @return int
   *   The shared funding amount.
   */
  public function getSharedClusterFunding() {
    $data = $this->getClusterSummaryData();
    return !empty($data->totals->shared) ? $data->totals->shared : 0;
  }

  /**
   * Extract cluster ids from the summary data.
   *
   * @return int[]
   *   An array of cluster ids.
   */
  public function getClusterIds() {
    $data = $this->getClusterSummaryData();
    $cluster_ids = array_map(fn ($cluster) => !empty($cluster->id) ? (int) $cluster->id : NULL, $data->clusters ?? []);
    $cluster_ids = array_merge($cluster_ids, $data->expected_cluster_ids ?? []);
    return array_values(array_unique(array_filter($cluster_ids)));
  }

  /**
   * Get the funding and requirements by cluster.
   *
   * @param object $data
   *   The result object from a grouped flow search.
   * @param array $cluster_ids
   *   Cluster ids to restrict to.
   *
   * @return array
   *   An array of funding data, mocked to be identical in structure to the
   *   result of PlanFundingSummaryQuery::getData()
   */
  public function getFundingDataByClusterIds($data, array $cluster_ids) {
    $funding_data = [
      'original_requirements' => NULL,
      'current_requirements' => NULL,
      'total_funding' => NULL,
      'funding_coverage' => NULL,
      'funding_gap' => NULL,
    ];
    $array_filter = ['id' => $cluster_ids];
    if (!empty($data->requirements) && !empty($data->requirements->objects)) {
      $requirements_objects = ArrayHelper::filterArray($data->requirements->objects, $array_filter);
      $funding_data['original_requirements'] = ArrayHelper::sumObjectsByProperty($requirements_objects, 'origRequirements');
      $funding_data['current_requirements'] = ArrayHelper::sumObjectsByProperty($requirements_objects, 'revisedRequirements');
    }
    $funding_breakdown = $data->report3->fundingTotals->objects[0]->objectsBreakdown ?? NULL;
    if (is_array($funding_breakdown)) {
      $funding_objects = ArrayHelper::filterArray($funding_breakdown, $array_filter);
      if (!empty($funding_objects)) {
        $funding_data['total_funding'] = ArrayHelper::sumObjectsByProperty($funding_objects, 'totalFunding');
      }
    }
    if ($funding_data['current_requirements'] !== NULL && $funding_data['total_funding'] !== NULL) {
      $funding_data['funding_coverage'] = $funding_data['current_requirements'] ? 100 / $funding_data['current_requirements'] * $funding_data['total_funding'] : 0;
      $funding_data['funding_gap'] = $funding_data['current_requirements'] > $funding_data['total_funding'] ? $funding_data['current_requirements'] - $funding_data['total_funding'] : 0;
    }
    return $funding_data;
  }

}
