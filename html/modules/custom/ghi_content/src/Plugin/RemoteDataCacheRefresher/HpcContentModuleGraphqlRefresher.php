<?php

namespace Drupal\ghi_content\Plugin\RemoteDataCacheRefresher;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_content\RemoteSource\RemoteSourceBaseHpcContentModule;
use Drupal\ghi_content\RemoteSource\RemoteSourceManager;
use Drupal\hpc_remote_data_cache\Attribute\RemoteDataCacheRefresher;
use Drupal\hpc_remote_data_cache\RemoteDataCacheItem;
use Drupal\hpc_remote_data_cache\RemoteDataCacheRefresherInterface;
use Drupal\hpc_remote_data_cache\RemoteDataCacheRefreshResult;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Refreshes cached HPC Content Module GraphQL responses.
 */
#[RemoteDataCacheRefresher(
  id: 'hpc_content_module_graphql',
  label: new TranslatableMarkup('HPC Content Module GraphQL'),
)]
class HpcContentModuleGraphqlRefresher extends PluginBase implements RemoteDataCacheRefresherInterface, ContainerFactoryPluginInterface {

  /**
   * The remote source manager.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager
   */
  protected RemoteSourceManager $remoteSourceManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->remoteSourceManager = $container->get('plugin.manager.remote_source');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function refresh(RemoteDataCacheItem $item): RemoteDataCacheRefreshResult {
    $context = $item->getContext();
    $remote_source_id = $context['remote_source_id'] ?? NULL;
    if (!$remote_source_id || !$this->remoteSourceManager->hasDefinition($remote_source_id)) {
      return RemoteDataCacheRefreshResult::failure('Missing or invalid remote source id.');
    }

    $remote_source = $this->remoteSourceManager->createInstance($remote_source_id);
    if (!$remote_source instanceof RemoteSourceBaseHpcContentModule) {
      return RemoteDataCacheRefreshResult::failure('Remote source does not support HPC Content Module GraphQL refreshes.');
    }

    $response = $remote_source->fetchRemoteGraphQlRequest($item->getRequestBody());
    if (!$response->getStatus()) {
      return RemoteDataCacheRefreshResult::failure('HPC Content Module GraphQL refresh failed with status ' . $response->getCode() . '.');
    }
    return RemoteDataCacheRefreshResult::success($response);
  }

}
