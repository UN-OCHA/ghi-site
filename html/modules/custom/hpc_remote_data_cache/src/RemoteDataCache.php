<?php

namespace Drupal\hpc_remote_data_cache;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;

/**
 * Persistent remote data cache with background refresh.
 */
class RemoteDataCache implements RemoteDataCacheInterface {

  private const QUEUE_ID = 'hpc_remote_data_cache_refresh';
  private const LOCK_PREFIX = 'hpc_remote_data_cache:';
  private const DEFAULT_QUEUE_LOCK_TTL = 30;

  /**
   * Constructs a remote data cache service.
   */
  public function __construct(
    private readonly CacheBackendInterface $cacheBackend,
    private readonly RemoteDataCacheIndexInterface $index,
    private readonly QueueFactory $queueFactory,
    private readonly LockBackendInterface $lock,
    private readonly TimeInterface $time,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly RemoteDataCacheRefresherManager $refresherManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    return (bool) $this->getSettings()->get('enabled');
  }

  /**
   * {@inheritdoc}
   */
  public function canServeExpiredOnError(): bool {
    return (bool) $this->getSettings()->get('serve_expired_on_error');
  }

  /**
   * {@inheritdoc}
   */
  public function buildCid(string $source, string $fingerprint): string {
    return $source . ':' . hash('sha256', $fingerprint);
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $cid): ?RemoteDataCacheItem {
    $data = $this->getItemData($cid);
    if ($data === NULL) {
      return NULL;
    }

    return $this->buildItem($cid, $data, $this->time->getRequestTime());
  }

  /**
   * {@inheritdoc}
   */
  public function set(string $cid, mixed $payload, array $metadata): void {
    $payload_size = strlen(serialize($payload));
    $max_payload_size = (int) $this->getSettings()->get('max_payload_size');
    if ($max_payload_size > 0 && $payload_size > $max_payload_size) {
      $this->loggerFactory->get('hpc_remote_data_cache')->warning('Skipped remote data cache item @cid because payload size @size exceeds limit @limit.', [
        '@cid' => $cid,
        '@size' => $payload_size,
        '@limit' => $max_payload_size,
      ]);
      return;
    }

    $now = $this->time->getRequestTime();
    $existing = $this->getItemData($cid);
    $fresh_ttl = $this->getPositiveInt($metadata['fresh_ttl'] ?? NULL, 'default_fresh_ttl');
    $stale_ttl = $this->getPositiveInt($metadata['stale_ttl'] ?? NULL, 'default_stale_ttl');
    $this->writeItemData($cid, [
      'refresher_id' => (string) ($metadata['refresher_id'] ?? ''),
      'endpoint_url' => (string) ($metadata['endpoint_url'] ?? ''),
      'request_body' => (string) ($metadata['request_body'] ?? ''),
      'context' => (array) ($metadata['context'] ?? []),
      'payload' => $payload,
      'cache_tags' => (array) ($metadata['cache_tags'] ?? []),
      'payload_size' => $payload_size,
      'created' => (int) ($existing['created'] ?? $now),
      'changed' => $now,
      'fetched' => $now,
      'fresh_until' => $now + $fresh_ttl,
      'stale_until' => $now + $fresh_ttl + $stale_ttl,
      'refresh_queued' => 0,
      'refreshing_until' => 0,
      'retry_after' => 0,
      'last_access' => $now,
      'fail_count' => 0,
      'last_error' => NULL,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function queueRefresh(RemoteDataCacheItem|string $item): bool {
    $item = is_string($item) ? $this->get($item) : $item;
    if (!$item || !$item->canQueueRefresh()) {
      return FALSE;
    }

    $lock_name = self::LOCK_PREFIX . $item->getCid() . ':queue';
    if (!$this->lock->acquire($lock_name, self::DEFAULT_QUEUE_LOCK_TTL)) {
      return FALSE;
    }

    try {
      // Re-read the item while holding the queue lock so parallel requests do
      // not enqueue the same stale response repeatedly.
      $item = $this->get($item->getCid());
      if (!$item || !$item->canQueueRefresh()) {
        return FALSE;
      }

      $data = $this->itemToData($item);
      $data['changed'] = $this->time->getRequestTime();
      $data['refresh_queued'] = TRUE;
      $this->writeItemData($item->getCid(), $data);

      $this->queueFactory->get(self::QUEUE_ID)->createItem((object) [
        'cid' => $item->getCid(),
      ]);
      return TRUE;
    }
    finally {
      $this->lock->release($lock_name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function refresh(string $cid): bool {
    $item = $this->get($cid);
    if (!$item) {
      return FALSE;
    }

    $lock_name = self::LOCK_PREFIX . $cid . ':refresh';
    $lock_ttl = max(1, $this->getPositiveInt(NULL, 'refresh_lock_ttl'));
    if (!$this->lock->acquire($lock_name, $lock_ttl)) {
      return FALSE;
    }

    $now = $this->time->getRequestTime();
    $data = $this->itemToData($item);
    $data['changed'] = $now;
    $data['refresh_queued'] = FALSE;
    $data['refreshing_until'] = $now + $lock_ttl;
    $this->writeItemData($cid, $data);
    $item = $this->buildItem($cid, $data, $now);

    try {
      /** @var \Drupal\hpc_remote_data_cache\RemoteDataCacheRefresherInterface $refresher */
      $refresher = $this->refresherManager->createInstance($item->getRefresherId());
      $result = $refresher->refresh($item);
      if ($result->isSuccess()) {
        $this->set($cid, $result->getData(), [
          'refresher_id' => $item->getRefresherId(),
          'endpoint_url' => $item->getEndpointUrl(),
          'request_body' => $item->getRequestBody(),
          'context' => $item->getContext(),
          'cache_tags' => $item->getCacheTags(),
          'fresh_ttl' => max(0, $item->getFreshUntil() - $item->getFetched()),
          'stale_ttl' => max(0, $item->getStaleUntil() - $item->getFreshUntil()),
        ]);
        return TRUE;
      }

      $this->markRefreshFailed($item, $result->getError() ?? 'Unknown refresh failure.');
      return FALSE;
    }
    catch (\Throwable $e) {
      $this->markRefreshFailed($item, $e->getMessage());
      return FALSE;
    }
    finally {
      $this->lock->release($lock_name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function prune(?int $limit = NULL): int {
    $limit = $limit ?? $this->getPositiveInt(NULL, 'prune_batch_size');
    if ($limit <= 0) {
      return 0;
    }

    $deleted = 0;
    $expired_retention_ttl = $this->getPositiveInt(NULL, 'expired_retention_ttl');
    $expired_cutoff = $this->time->getRequestTime() - $expired_retention_ttl;
    $deleted += $this->deleteIndexedItems($this->index->getExpiredCids($expired_cutoff, $limit));

    $remaining = $limit - $deleted;
    $max_items = $this->getPositiveInt(NULL, 'max_items');
    if ($remaining > 0 && $max_items > 0) {
      $overflow = $this->index->count() - $max_items;
      if ($overflow > 0) {
        $deleted += $this->deleteIndexedItems($this->index->getOldestCids(min($overflow, $remaining)));
      }
    }

    return $deleted;
  }

  /**
   * Mark a cache refresh as failed.
   *
   * @param \Drupal\hpc_remote_data_cache\RemoteDataCacheItem $item
   *   The cache item.
   * @param string $error
   *   The refresh error.
   */
  private function markRefreshFailed(RemoteDataCacheItem $item, string $error): void {
    $now = $this->time->getRequestTime();
    $fail_count = $item->getFailCount() + 1;
    $base_backoff = $this->getPositiveInt(NULL, 'refresh_retry_base');
    $retry_after = $base_backoff > 0 ? $now + min($base_backoff * $fail_count, 3600) : 0;
    $data = $this->itemToData($item);
    $data['changed'] = $now;
    $data['refresh_queued'] = FALSE;
    $data['refreshing_until'] = 0;
    $data['retry_after'] = $retry_after;
    $data['fail_count'] = $fail_count;
    $data['last_error'] = $error;
    $this->writeItemData($item->getCid(), $data);
  }

  /**
   * Get stored cache item data.
   *
   * @param string $cid
   *   The cache id.
   *
   * @return array|null
   *   The stored item data, or NULL if not found.
   */
  private function getItemData(string $cid): ?array {
    $cache = $this->cacheBackend->get($cid);
    return $cache && is_array($cache->data) ? $cache->data : NULL;
  }

  /**
   * Store cache item data in the permanent cache bin.
   *
   * @param string $cid
   *   The cache id.
   * @param array $data
   *   The item data.
   */
  private function writeItemData(string $cid, array $data): void {
    $tags = Cache::mergeTags([
      'hpc_remote_data_cache',
      'hpc_remote_data_cache:' . ($data['refresher_id'] ?? 'unknown'),
    ], (array) ($data['cache_tags'] ?? []));
    $this->cacheBackend->set($cid, $data, Cache::PERMANENT, $tags);
    $this->index->upsert($cid, $data);
  }

  /**
   * Delete indexed cache items from the payload cache and metadata index.
   *
   * @param string[] $cids
   *   Cache ids to delete.
   *
   * @return int
   *   The number of cache ids deleted.
   */
  private function deleteIndexedItems(array $cids): int {
    $cids = array_values(array_unique(array_filter($cids)));
    if ($cids === []) {
      return 0;
    }

    $this->cacheBackend->deleteMultiple($cids);
    $this->index->deleteMultiple($cids);
    return count($cids);
  }

  /**
   * Convert a cache item back into stored data.
   *
   * @param \Drupal\hpc_remote_data_cache\RemoteDataCacheItem $item
   *   The cache item.
   *
   * @return array
   *   The cache item data.
   */
  private function itemToData(RemoteDataCacheItem $item): array {
    return [
      'refresher_id' => $item->getRefresherId(),
      'endpoint_url' => $item->getEndpointUrl(),
      'request_body' => $item->getRequestBody(),
      'context' => $item->getContext(),
      'payload' => $item->getPayload(),
      'cache_tags' => $item->getCacheTags(),
      'payload_size' => $item->getPayloadSize(),
      'created' => $item->getCreated(),
      'changed' => $item->getChanged(),
      'fetched' => $item->getFetched(),
      'fresh_until' => $item->getFreshUntil(),
      'stale_until' => $item->getStaleUntil(),
      'refresh_queued' => $item->isRefreshQueued(),
      'refreshing_until' => $item->getRefreshingUntil(),
      'retry_after' => $item->getRetryAfter(),
      'last_access' => $item->getLastAccess(),
      'fail_count' => $item->getFailCount(),
      'last_error' => $item->getLastError(),
    ];
  }

  /**
   * Build a cache item from stored cache-bin data.
   *
   * @param string $cid
   *   The cache id.
   * @param array $data
   *   The stored cache data.
   * @param int $request_time
   *   The request timestamp.
   *
   * @return \Drupal\hpc_remote_data_cache\RemoteDataCacheItem
   *   The cache item.
   */
  private function buildItem(string $cid, array $data, int $request_time): RemoteDataCacheItem {
    return new RemoteDataCacheItem(
      $cid,
      (string) ($data['refresher_id'] ?? ''),
      (string) ($data['endpoint_url'] ?? ''),
      (string) ($data['request_body'] ?? ''),
      (array) ($data['context'] ?? []),
      $data['payload'] ?? NULL,
      (int) ($data['created'] ?? 0),
      (int) ($data['changed'] ?? 0),
      (int) ($data['fetched'] ?? 0),
      (int) ($data['fresh_until'] ?? 0),
      (int) ($data['stale_until'] ?? 0),
      (bool) ($data['refresh_queued'] ?? FALSE),
      (int) ($data['refreshing_until'] ?? 0),
      (int) ($data['retry_after'] ?? 0),
      (int) ($data['last_access'] ?? 0),
      (int) ($data['fail_count'] ?? 0),
      isset($data['last_error']) ? (string) $data['last_error'] : NULL,
      (int) ($data['payload_size'] ?? 0),
      $request_time,
      (array) ($data['cache_tags'] ?? []),
    );
  }

  /**
   * Get a positive integer from metadata or config.
   *
   * @param mixed $value
   *   The metadata value.
   * @param string $config_key
   *   The config key to use as fallback.
   *
   * @return int
   *   The positive integer.
   */
  private function getPositiveInt(mixed $value, string $config_key): int {
    $value = $value ?? $this->getSettings()->get($config_key);
    return max(0, (int) $value);
  }

  /**
   * Get the remote data cache settings.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The settings config.
   */
  private function getSettings() {
    return $this->configFactory->get('hpc_remote_data_cache.settings');
  }

}
