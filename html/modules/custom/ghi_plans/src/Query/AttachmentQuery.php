<?php

namespace Drupal\ghi_plans\Query;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ghi_plans\Helpers\AttachmentHelper;
use GuzzleHttp\ClientInterface;
use Drupal\hpc_api\Query\EndpointQuery;

/**
 * Query class for fetching plan data with a focus on plan entities.
 */
class AttachmentQuery extends EndpointQuery {

  /**
   * Constructs a new PlanEntitiesQuery object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, CacheBackendInterface $cache, KillSwitch $kill_switch, ClientInterface $http_client, AccountProxyInterface $user) {
    parent::__construct($config_factory, $logger_factory, $cache, $kill_switch, $http_client, $user);

    $this->endpointUrl = 'public/attachment/{attachment_id}';
    // @codingStandardsIgnoreStart
    // @todo Implement this once HID login has been added.
    // if ($this->user->isAuthenticated()) {
    //   $this->endpointUrl = 'attachment/{attachment_id}';
    // }
    // @codingStandardsIgnoreEnd
    $this->endpointVersion = 'v2';
    $this->endpointArgs = [
      'disaggregation' => 'false',
      'version' => 'current',
    ];
  }

  /**
   * Get an attachment by id.
   *
   * @param int $attachment_id
   *   The attachmen id to query.
   *
   * @return object
   *   The processed attachment object.
   */
  public function getAttachment($attachment_id) {
    $this->setPlaceholder('attachment_id', $attachment_id);
    $data = $this->getData();
    if (empty($data)) {
      return NULL;
    }

    return AttachmentHelper::processAttachment($data);
  }

}
