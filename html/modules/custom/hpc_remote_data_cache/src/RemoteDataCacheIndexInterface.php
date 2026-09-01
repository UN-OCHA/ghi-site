<?php

namespace Drupal\hpc_remote_data_cache;

/**
 * Index storage for queryable remote data cache metadata.
 */
interface RemoteDataCacheIndexInterface {

  /**
   * Store or update index metadata for a cache item.
   *
   * @param string $cid
   *   The cache id.
   * @param array $data
   *   The stored cache item data.
   */
  public function upsert(string $cid, array $data): void;

  /**
   * Delete cache item metadata from the index.
   *
   * @param string[] $cids
   *   Cache ids to delete.
   */
  public function deleteMultiple(array $cids): void;

  /**
   * Get expired cache ids eligible for pruning.
   *
   * @param int $cutoff
   *   Items whose stale-until timestamp is older than this cutoff are eligible.
   * @param int $limit
   *   The maximum number of cache ids to return.
   * @param string[] $excluded_sources
   *   Source prefixes to exclude from pruning.
   *
   * @return string[]
   *   Cache ids.
   */
  public function getExpiredCids(int $cutoff, int $limit, array $excluded_sources = []): array;

  /**
   * Count indexed cache items.
   *
   * @param string[] $excluded_sources
   *   Source prefixes to exclude from the count.
   *
   * @return int
   *   The indexed item count.
   */
  public function count(array $excluded_sources = []): int;

  /**
   * Get the oldest indexed cache ids.
   *
   * @param int $limit
   *   The maximum number of cache ids to return.
   * @param string[] $excluded_sources
   *   Source prefixes to exclude from pruning.
   *
   * @return string[]
   *   Cache ids.
   */
  public function getOldestCids(int $limit, array $excluded_sources = []): array;

}
