# HPC Remote Data Cache

`hpc_remote_data_cache` provides a persistent remote data cache with background
refresh for remote data responses.

The module is intended for expensive remote data lookups where `drush cr`
should not force every request to fetch the same data from the remote system
again. It stores response payloads and refresh metadata in the PCB-backed cache
bin `cache.hpc_remote_data_cache`.

## How It Works

Consumers store successful remote responses through the
`hpc_remote_data_cache.cache` service. Each entry contains:

- the response payload
- the remote endpoint and request body needed to refresh it
- the refresher plugin id
- optional refresher context
- optional caller cache tags
- fresh/stale timestamps
- refresh queue and retry metadata

When a consumer asks for a cached item:

- Fresh entries are returned immediately.
- Stale entries are returned immediately and a background refresh is queued.
- Expired entries are not returned for normal requests.
- Expired entries may be returned after a remote fetch error if
  `serve_expired_on_error` is enabled.

Background refreshes are handled by the `hpc_remote_data_cache_refresh` queue
worker. The worker delegates the actual remote request to the refresher plugin
recorded on the cache item.

## Storage

The cache bin is defined in `hpc_remote_data_cache.services.yml`:

```yaml
cache.hpc_remote_data_cache:
  class: Drupal\Core\Cache\CacheBackendInterface
  tags:
    - { name: cache.bin, default_backend: cache.backend.permanent_database }
  factory: cache_factory:get
  arguments: [hpc_remote_data_cache]
```

Because the bin uses PCB's permanent database backend, `drush cr` does not clear
the cached remote responses. Drupal cache-tag invalidation still applies.

Queryable cache metadata is stored separately in
`hpc_remote_data_cache_index`. The payload remains in the PCB cache table, while
the index stores timestamps, payload size, source, and refresh state so pruning
can use indexed SQL instead of deserializing cache blobs.

Existing cache rows are not backfilled into the index automatically. A row is
indexed when the remote data cache service writes or updates it.

## Current Consumers

- `hpc_api`: Fabric GraphQL responses via refresher plugin `fabric_graphql`.
- `hpc_api`: legacy HPC API endpoint responses via refresher plugin
  `hpc_api_endpoint`.
- `ghi_content`: HPC Content Module GraphQL responses via refresher plugin
  `hpc_content_module_graphql`.

`ghi_content` keeps high-cardinality title searches on the normal cache path so
editor/autocomplete searches do not fill the permanent cache bin.

## Cache Tags

Every item is stored with these tags:

- `hpc_remote_data_cache`
- `hpc_remote_data_cache:{refresher_id}`

Consumers can also pass their own cache tags. For example, `ghi_content` stores
tags such as `hpc_content_module:article:123` and
`hpc_content_module:document:456`.

Unlike stale entries, tag-invalidated entries are treated as cache misses. This
keeps caller invalidation authoritative even though the bin survives cache
rebuilds.

## Configuration

The default settings are in `hpc_remote_data_cache.settings`:

- `enabled`: turn the remote data cache on or off.
- `default_fresh_ttl`: seconds an item is considered fresh.
- `default_stale_ttl`: seconds older cached data can still be served while a
  background refresh is queued.
- `refresh_lock_ttl`: lock duration for refresh workers.
- `refresh_retry_base`: base retry delay after refresh failures.
- `serve_expired_on_error`: allow expired data as a fallback after remote
  errors.
- `max_payload_size`: optional maximum serialized payload size. `0` means no
  limit.
- `expired_retention_ttl`: seconds to keep expired items available for
  `serve_expired_on_error` before pruning can delete them.
- `max_items`: maximum number of indexed items to retain. `0` disables this cap.
- `prune_batch_size`: maximum number of indexed items to delete during one cron
  run.
- `prune_excluded_sources`: source prefixes that pruning must not delete or
  count against `max_items`. By default, legacy HPC API endpoint responses are
  excluded because that remote system is outside this site's control and the
  stored expired response can still be useful as an error fallback.

## Pruning

Cron prunes indexed cache entries in two passes:

- items whose `stale_until` timestamp is older than `expired_retention_ttl`
- oldest indexed items over the configured `max_items` cap

Sources listed in `prune_excluded_sources` are skipped by both passes and are
not counted toward the `max_items` overflow calculation.

Pruning deletes both the PCB cache payload row and the matching index row.
Because the index is maintained on writes, pruning does not deserialize payloads.

## Local Development

For local development, the most predictable workflow is usually to bypass
background refresh caching entirely so the request you make is the request that
fetches remote data. Add a local config override in `settings.local.php`:

```php
$config['hpc_remote_data_cache.settings']['enabled'] = FALSE;
```

When you specifically want to test background refresh locally, keep the cache
enabled and run the refresh queue manually after stale entries are queued:

```bash
fin drush queue:run hpc_remote_data_cache_refresh
```

## Clearing The Cache

You usually should not need to clear this cache manually.

Normal data changes should invalidate specific cache tags, and stale entries
refresh themselves in the background. The whole point of this cache is that
routine `drush cr` does not force cold remote fetches.

When manual clearing is needed, clear only this bin:

```bash
fin drush pcb:flush hpc_remote_data_cache
```

The PCB alias is also available:

```bash
fin drush pcbf hpc_remote_data_cache
```

To list permanent cache bins:

```bash
fin drush pcb:list
```

To clear all PCB-backed permanent bins, use this carefully:

```bash
fin drush pcb:flush-all
```

You can also clear the bin through the Drupal admin UI at
`/admin/config/development/performance`. PCB adds a button for each permanent
cache bin.

For targeted invalidation, invalidate cache tags instead of deleting the whole
bin:

```bash
fin drush ev '\Drupal::service("cache_tags.invalidator")->invalidateTags(["hpc_remote_data_cache:fabric_graphql"]);'
fin drush ev '\Drupal::service("cache_tags.invalidator")->invalidateTags(["hpc_remote_data_cache:hpc_content_module_graphql"]);'
```

Programmatically, the whole bin can be deleted with:

```php
\Drupal::service('cache.hpc_remote_data_cache')->deleteAllPermanent();
```

Use a full bin clear for cases such as corrupted cached payloads, remote API
contract changes, debugging, or when you explicitly want to measure true
cold-cache behavior.
