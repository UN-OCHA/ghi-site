<?php

namespace Drupal\ghi_plans\Query;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Session\AccountProxyInterface;
use GuzzleHttp\ClientInterface;
use Drupal\hpc_api\Query\EndpointQuery;

/**
 * Query class for fetching icons.
 */
class IconQuery extends EndpointQuery {

  /**
   * Constructs a new IconQuery object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, CacheBackendInterface $cache, KillSwitch $kill_switch, ClientInterface $http_client, AccountProxyInterface $user) {
    parent::__construct($config_factory, $logger_factory, $cache, $kill_switch, $http_client, $user);

    $this->endpointUrl = 'icon/{icon}';
    $this->endpointVersion = 'v2';
  }

  /**
   * Get tagged clusters for the given plan id.
   *
   * @param string $icon
   *   The identifier of the icon.
   *
   * @return string|null
   *   An array of cluster objects, keyed by the cluster id.
   */
  public function getIconEmbedCode($icon) {
    if (empty($icon) || $icon == 'blank_icon') {
      return NULL;
    }
    $this->setPlaceholder('icon', $icon);
    $svg_data = $this->getData();
    $svg_content = $svg_data ? $svg_data->svg : NULL;
    return '<div class="cluster-icon">' . $svg_content . '</div>';
  }

}
