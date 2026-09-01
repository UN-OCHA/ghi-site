<?php

namespace Drupal\hpc_remote_data_cache\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\hpc_remote_data_cache\RemoteDataCacheInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker for refreshing stale remote data cache entries.
 *
 * @QueueWorker(
 *   id = "hpc_remote_data_cache_refresh",
 *   title = @Translation("Refresh remote data cache entries"),
 *   cron = {"time" = 60}
 * )
 */
class RemoteDataCacheRefresh extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The remote data cache service.
   *
   * @var \Drupal\hpc_remote_data_cache\RemoteDataCacheInterface
   */
  protected RemoteDataCacheInterface $remoteDataCache;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->remoteDataCache = $container->get('hpc_remote_data_cache.cache');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (empty($data->cid)) {
      return;
    }
    $this->remoteDataCache->refresh((string) $data->cid);
  }

}
