<?php

namespace Drupal\hpc_api\Plugin\RemoteDataCacheRefresher;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_remote_data_cache\Attribute\RemoteDataCacheRefresher;
use Drupal\hpc_remote_data_cache\RemoteDataCacheItem;
use Drupal\hpc_remote_data_cache\RemoteDataCacheRefresherInterface;
use Drupal\hpc_remote_data_cache\RemoteDataCacheRefreshResult;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Refreshes cached legacy HPC API endpoint responses.
 */
#[RemoteDataCacheRefresher(
  id: 'hpc_api_endpoint',
  label: new TranslatableMarkup('HPC API endpoint'),
)]
class HpcEndpointRefresher extends PluginBase implements RemoteDataCacheRefresherInterface, ContainerFactoryPluginInterface {

  /**
   * The endpoint query service.
   *
   * @var \Drupal\hpc_api\Query\EndpointQuery
   */
  protected EndpointQuery $endpointQuery;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->endpointQuery = $container->get('hpc_api.endpoint_query');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function refresh(RemoteDataCacheItem $item): RemoteDataCacheRefreshResult {
    $context = $item->getContext();
    $error = NULL;
    $data = $this->endpointQuery->fetchRemoteEndpointResponse(
      $item->getEndpointUrl(),
      $context['auth_method'] ?? EndpointQuery::AUTH_METHOD_BASIC,
      $error,
    );

    return $data === FALSE ? RemoteDataCacheRefreshResult::failure($error ?? 'HPC API endpoint refresh failed.') : RemoteDataCacheRefreshResult::success($data);
  }

}
