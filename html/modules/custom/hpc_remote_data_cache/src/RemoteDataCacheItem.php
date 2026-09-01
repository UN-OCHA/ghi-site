<?php

namespace Drupal\hpc_remote_data_cache;

/**
 * Value object for a persistent remote data cache item.
 */
class RemoteDataCacheItem {

  public const STATE_FRESH = 'fresh';
  public const STATE_STALE = 'stale';
  public const STATE_EXPIRED = 'expired';

  /**
   * Constructs a remote data cache item.
   */
  public function __construct(
    private readonly string $cid,
    private readonly string $refresherId,
    private readonly string $endpointUrl,
    private readonly string $requestBody,
    private readonly array $context,
    private readonly mixed $payload,
    private readonly int $created,
    private readonly int $changed,
    private readonly int $fetched,
    private readonly int $freshUntil,
    private readonly int $staleUntil,
    private readonly bool $refreshQueued,
    private readonly int $refreshingUntil,
    private readonly int $retryAfter,
    private readonly int $lastAccess,
    private readonly int $failCount,
    private readonly ?string $lastError,
    private readonly int $payloadSize,
    private readonly int $requestTime,
    private readonly array $cacheTags = [],
  ) {}

  /**
   * Get the cache id.
   *
   * @return string
   *   The cache id.
   */
  public function getCid(): string {
    return $this->cid;
  }

  /**
   * Get the refresher plugin id.
   *
   * @return string
   *   The refresher plugin id.
   */
  public function getRefresherId(): string {
    return $this->refresherId;
  }

  /**
   * Get the remote endpoint URL.
   *
   * @return string
   *   The remote endpoint URL.
   */
  public function getEndpointUrl(): string {
    return $this->endpointUrl;
  }

  /**
   * Get the remote request body.
   *
   * @return string
   *   The remote request body.
   */
  public function getRequestBody(): string {
    return $this->requestBody;
  }

  /**
   * Get refresher context.
   *
   * @return array
   *   The refresher context.
   */
  public function getContext(): array {
    return $this->context;
  }

  /**
   * Get the cached payload.
   *
   * @return mixed
   *   The cached payload.
   */
  public function getPayload(): mixed {
    return $this->payload;
  }

  /**
   * Get the item creation timestamp.
   *
   * @return int
   *   The item creation timestamp.
   */
  public function getCreated(): int {
    return $this->created;
  }

  /**
   * Get the item changed timestamp.
   *
   * @return int
   *   The item changed timestamp.
   */
  public function getChanged(): int {
    return $this->changed;
  }

  /**
   * Get the successful fetch timestamp.
   *
   * @return int
   *   The successful fetch timestamp.
   */
  public function getFetched(): int {
    return $this->fetched;
  }

  /**
   * Get the fresh-until timestamp.
   *
   * @return int
   *   The fresh-until timestamp.
   */
  public function getFreshUntil(): int {
    return $this->freshUntil;
  }

  /**
   * Get the stale-until timestamp.
   *
   * @return int
   *   The stale-until timestamp.
   */
  public function getStaleUntil(): int {
    return $this->staleUntil;
  }

  /**
   * Whether a refresh is queued.
   *
   * @return bool
   *   TRUE if a refresh is queued, FALSE otherwise.
   */
  public function isRefreshQueued(): bool {
    return $this->refreshQueued;
  }

  /**
   * Get the refreshing-until timestamp.
   *
   * @return int
   *   The refreshing-until timestamp.
   */
  public function getRefreshingUntil(): int {
    return $this->refreshingUntil;
  }

  /**
   * Get the retry-after timestamp.
   *
   * @return int
   *   The retry-after timestamp.
   */
  public function getRetryAfter(): int {
    return $this->retryAfter;
  }

  /**
   * Get the last-access timestamp.
   *
   * @return int
   *   The last-access timestamp.
   */
  public function getLastAccess(): int {
    return $this->lastAccess;
  }

  /**
   * Get the consecutive failure count.
   *
   * @return int
   *   The consecutive failure count.
   */
  public function getFailCount(): int {
    return $this->failCount;
  }

  /**
   * Get the last refresh error.
   *
   * @return string|null
   *   The last refresh error.
   */
  public function getLastError(): ?string {
    return $this->lastError;
  }

  /**
   * Get the serialized payload size.
   *
   * @return int
   *   The serialized payload size in bytes.
   */
  public function getPayloadSize(): int {
    return $this->payloadSize;
  }

  /**
   * Get cache tags attached to the item.
   *
   * @return string[]
   *   The cache tags.
   */
  public function getCacheTags(): array {
    return $this->cacheTags;
  }

  /**
   * Get the current item state.
   *
   * @return string
   *   One of the STATE_* constants.
   */
  public function getState(): string {
    if ($this->requestTime <= $this->freshUntil) {
      return self::STATE_FRESH;
    }
    if ($this->requestTime <= $this->staleUntil) {
      return self::STATE_STALE;
    }
    return self::STATE_EXPIRED;
  }

  /**
   * Whether the item is fresh.
   *
   * @return bool
   *   TRUE if the item is fresh, FALSE otherwise.
   */
  public function isFresh(): bool {
    return $this->getState() === self::STATE_FRESH;
  }

  /**
   * Whether the item is stale.
   *
   * @return bool
   *   TRUE if the item is stale, FALSE otherwise.
   */
  public function isStale(): bool {
    return $this->getState() === self::STATE_STALE;
  }

  /**
   * Whether the item is expired.
   *
   * @return bool
   *   TRUE if the item is expired, FALSE otherwise.
   */
  public function isExpired(): bool {
    return $this->getState() === self::STATE_EXPIRED;
  }

  /**
   * Whether the item can be refreshed now.
   *
   * @return bool
   *   TRUE if the item can be refreshed, FALSE otherwise.
   */
  public function canQueueRefresh(): bool {
    return !$this->refreshQueued && $this->requestTime >= $this->refreshingUntil && $this->requestTime >= $this->retryAfter;
  }

}
