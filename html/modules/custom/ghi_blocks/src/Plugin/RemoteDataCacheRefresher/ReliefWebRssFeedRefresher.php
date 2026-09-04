<?php

namespace Drupal\ghi_blocks\Plugin\RemoteDataCacheRefresher;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_blocks\ReliefWeb\ReliefWebRssFeed;
use Drupal\hpc_remote_data_cache\Attribute\RemoteDataCacheRefresher;
use Drupal\hpc_remote_data_cache\RemoteDataCacheItem;
use Drupal\hpc_remote_data_cache\RemoteDataCacheRefresherInterface;
use Drupal\hpc_remote_data_cache\RemoteDataCacheRefreshResult;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Refreshes cached ReliefWeb RSS feed responses.
 */
#[RemoteDataCacheRefresher(
  id: ReliefWebRssFeed::REFRESHER_ID,
  label: new TranslatableMarkup('ReliefWeb RSS feed'),
)]
class ReliefWebRssFeedRefresher extends PluginBase implements RemoteDataCacheRefresherInterface, ContainerFactoryPluginInterface {

  /**
   * The ReliefWeb RSS feed service.
   *
   * @var \Drupal\ghi_blocks\ReliefWeb\ReliefWebRssFeed
   */
  protected ReliefWebRssFeed $reliefWebRssFeed;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->reliefWebRssFeed = $container->get('ghi_blocks.reliefweb_rss_feed');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function refresh(RemoteDataCacheItem $item): RemoteDataCacheRefreshResult {
    $items = $this->reliefWebRssFeed->fetchRemoteItems($item->getEndpointUrl());
    return $items === NULL ? RemoteDataCacheRefreshResult::failure('ReliefWeb RSS feed refresh failed.') : RemoteDataCacheRefreshResult::success($items);
  }

}
