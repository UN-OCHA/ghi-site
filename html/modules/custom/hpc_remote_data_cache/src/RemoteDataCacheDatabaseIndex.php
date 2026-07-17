<?php

namespace Drupal\hpc_remote_data_cache;

use Drupal\Core\Database\Connection;

/**
 * Database-backed index for queryable remote data cache metadata.
 */
class RemoteDataCacheDatabaseIndex implements RemoteDataCacheIndexInterface {

  private const INDEX_TABLE = 'hpc_remote_data_cache_index';

  /**
   * Whether the index table exists.
   *
   * @var bool|null
   */
  private ?bool $indexTableExists = NULL;

  /**
   * Constructs a remote data cache database index service.
   */
  public function __construct(
    private readonly Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function upsert(string $cid, array $data): void {
    if (!$this->indexTableExists()) {
      return;
    }

    $fields = $this->buildFields($cid, $data);
    $this->database->upsert(self::INDEX_TABLE)
      ->key('cid')
      ->fields(array_keys($fields))
      ->values(array_values($fields))
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids): void {
    if (!$this->indexTableExists()) {
      return;
    }

    $cids = $this->normalizeCids($cids);
    foreach (array_chunk($cids, 1000) as $chunk) {
      $this->database->delete(self::INDEX_TABLE)
        ->condition('cid', $chunk, 'IN')
        ->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getExpiredCids(int $cutoff, int $limit): array {
    if (!$this->indexTableExists() || $limit <= 0) {
      return [];
    }

    $query = $this->database->select(self::INDEX_TABLE, 'i');
    $query->fields('i', ['cid']);
    $query->condition('i.stale_until', 0, '>');
    $query->condition('i.stale_until', $cutoff, '<');
    $query->orderBy('i.stale_until', 'ASC');
    $query->orderBy('i.changed', 'ASC');
    $query->range(0, $limit);
    return $query->execute()->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function count(): int {
    if (!$this->indexTableExists()) {
      return 0;
    }

    return (int) $this->database->select(self::INDEX_TABLE, 'i')->countQuery()->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getOldestCids(int $limit): array {
    if (!$this->indexTableExists() || $limit <= 0) {
      return [];
    }

    $query = $this->database->select(self::INDEX_TABLE, 'i');
    $query->fields('i', ['cid']);
    $query->orderBy('i.stale_until', 'ASC');
    $query->orderBy('i.changed', 'ASC');
    $query->orderBy('i.cid', 'ASC');
    $query->range(0, $limit);
    return $query->execute()->fetchCol();
  }

  /**
   * Build index fields from stored cache item data.
   *
   * @param string $cid
   *   The cache id.
   * @param array $data
   *   The stored cache item data.
   *
   * @return array
   *   Indexed field values.
   */
  private function buildFields(string $cid, array $data): array {
    return [
      'cid' => $cid,
      'source' => $this->getSourceFromCid($cid),
      'refresher_id' => (string) ($data['refresher_id'] ?? ''),
      'payload_size' => (int) ($data['payload_size'] ?? 0),
      'created' => (int) ($data['created'] ?? 0),
      'changed' => (int) ($data['changed'] ?? 0),
      'fetched' => (int) ($data['fetched'] ?? 0),
      'fresh_until' => (int) ($data['fresh_until'] ?? 0),
      'stale_until' => (int) ($data['stale_until'] ?? 0),
      'refresh_queued' => !empty($data['refresh_queued']) ? 1 : 0,
      'refreshing_until' => (int) ($data['refreshing_until'] ?? 0),
      'retry_after' => (int) ($data['retry_after'] ?? 0),
      'fail_count' => (int) ($data['fail_count'] ?? 0),
      'last_access' => (int) ($data['last_access'] ?? 0),
    ];
  }

  /**
   * Get the source prefix from a cache id.
   *
   * @param string $cid
   *   The cache id.
   *
   * @return string
   *   The source prefix.
   */
  private function getSourceFromCid(string $cid): string {
    return explode(':', $cid, 2)[0] ?? '';
  }

  /**
   * Normalize and de-duplicate cache ids.
   *
   * @param string[] $cids
   *   The raw cache ids.
   *
   * @return string[]
   *   Normalized cache ids.
   */
  private function normalizeCids(array $cids): array {
    return array_values(array_unique(array_filter(array_map('strval', $cids))));
  }

  /**
   * Whether the index table exists.
   *
   * @return bool
   *   TRUE if the table exists, FALSE otherwise.
   */
  private function indexTableExists(): bool {
    return $this->indexTableExists ??= $this->database->schema()->tableExists(self::INDEX_TABLE);
  }

}
