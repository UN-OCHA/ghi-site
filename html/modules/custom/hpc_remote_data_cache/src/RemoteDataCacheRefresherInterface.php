<?php

namespace Drupal\hpc_remote_data_cache;

/**
 * Interface for remote data cache refresher plugins.
 */
interface RemoteDataCacheRefresherInterface {

  /**
   * Refresh the given remote data cache item.
   *
   * @param \Drupal\hpc_remote_data_cache\RemoteDataCacheItem $item
   *   The remote data cache item.
   *
   * @return \Drupal\hpc_remote_data_cache\RemoteDataCacheRefreshResult
   *   The refresh result.
   */
  public function refresh(RemoteDataCacheItem $item): RemoteDataCacheRefreshResult;

}
