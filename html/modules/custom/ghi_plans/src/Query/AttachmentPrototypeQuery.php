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
 * Query class for fetching an attachment prototype.
 */
class AttachmentPrototypeQuery extends EndpointQuery {

  /**
   * Constructs a new AttachmentPrototypeQuery object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, CacheBackendInterface $cache, KillSwitch $kill_switch, ClientInterface $http_client, AccountProxyInterface $user) {
    parent::__construct($config_factory, $logger_factory, $cache, $kill_switch, $http_client, $user);

    $this->endpointUrl = 'public/plan/{plan_id}/attachment-prototype';
    // @codingStandardsIgnoreStart
    // @todo Implement this once HID login has been added.
    // if ($this->user->isAuthenticated()) {
    //   $this->endpointUrl = 'attachment/{attachment_id}';
    // }
    // @codingStandardsIgnoreEnd
    $this->endpointVersion = 'v2';
  }

  /**
   * Get an attachment prototype by plan and prototype ID.
   *
   * @param int $plan_id
   *   The id of the plan to which a prototype belongs.
   * @param int $prototype_id
   *   The id of the prototype.
   *
   * @return object
   *   The processed attachment object.
   */
  public function getPrototypeByPlanAndId($plan_id, $prototype_id) {
    $this->setPlaceholder('plan_id', $plan_id);
    $data = $this->getData();
    if (empty($data)) {
      return NULL;
    }

    foreach ($data as $prototype) {
      if ($prototype->id == $prototype_id) {
        return $prototype;
      }
    }
    return NULL;
  }

}
