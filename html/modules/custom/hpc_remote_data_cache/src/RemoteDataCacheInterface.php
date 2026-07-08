<?php

namespace Drupal\hpc_remote_data_cache;

/**
 * Interface for persistent remote data caches with background refresh.
 */
interface RemoteDataCacheInterface {

  /**
   * Whether the remote data cache is enabled.
   *
   * @return bool
   *   TRUE if enabled, FALSE otherwise.
   */
  public function isEnabled(): bool;

  /**
   * Whether expired data may be served after a refresh failure.
   *
   * @return bool
   *   TRUE if expired data may be served on error, FALSE otherwise.
   */
  public function canServeExpiredOnError(): bool;

  /**
   * Build a stable cache id for a source and request fingerprint.
   *
   * @param string $source
   *   The remote data source id.
   * @param string $fingerprint
   *   The request fingerprint.
   *
   * @return string
   *   The cache id.
   */
  public function buildCid(string $source, string $fingerprint): string;

  /**
   * Get a remote data cache item.
   *
   * @param string $cid
   *   The cache id.
   *
   * @return \Drupal\hpc_remote_data_cache\RemoteDataCacheItem|null
   *   The cache item, or NULL if not found.
   */
  public function get(string $cid): ?RemoteDataCacheItem;

  /**
   * Store a successful remote response.
   *
   * @param string $cid
   *   The cache id.
   * @param mixed $payload
   *   The response payload.
   * @param array $metadata
   *   Cache metadata.
   */
  public function set(string $cid, mixed $payload, array $metadata): void;

  /**
   * Queue a refresh for a cache item.
   *
   * @param \Drupal\hpc_remote_data_cache\RemoteDataCacheItem|string $item
   *   The cache item or cache id.
   *
   * @return bool
   *   TRUE if a refresh was queued, FALSE otherwise.
   */
  public function queueRefresh(RemoteDataCacheItem|string $item): bool;

  /**
   * Refresh a cache item immediately.
   *
   * @param string $cid
   *   The cache id.
   *
   * @return bool
   *   TRUE if the item was refreshed, FALSE otherwise.
   */
  public function refresh(string $cid): bool;

}
