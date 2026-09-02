<?php

namespace Drupal\hpc_api\Plugin\RemoteDataCacheRefresher;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hpc_api\Query\FabricClient;
use Drupal\hpc_remote_data_cache\Attribute\RemoteDataCacheRefresher;
use Drupal\hpc_remote_data_cache\RemoteDataCacheItem;
use Drupal\hpc_remote_data_cache\RemoteDataCacheRefresherInterface;
use Drupal\hpc_remote_data_cache\RemoteDataCacheRefreshResult;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Refreshes cached Fabric GraphQL responses.
 */
#[RemoteDataCacheRefresher(
  id: 'fabric_graphql',
  label: new TranslatableMarkup('Fabric GraphQL'),
)]
class FabricGraphqlRefresher extends PluginBase implements RemoteDataCacheRefresherInterface, ContainerFactoryPluginInterface {

  /**
   * The Fabric client.
   *
   * @var \Drupal\hpc_api\Query\FabricClient
   */
  protected FabricClient $fabricClient;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->fabricClient = $container->get('hpc_api.fabric_client');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function refresh(RemoteDataCacheItem $item): RemoteDataCacheRefreshResult {
    $context = $item->getContext();
    $error = NULL;
    $data = $this->fabricClient->fetchRemoteGraphQlRequest(
      $item->getRequestBody(),
      $context['query'] ?? NULL,
      $error,
      $item->getEndpointUrl(),
    );

    return $data === FALSE ? RemoteDataCacheRefreshResult::failure($error ?? 'Fabric GraphQL refresh failed.') : RemoteDataCacheRefreshResult::success($data);
  }

}
