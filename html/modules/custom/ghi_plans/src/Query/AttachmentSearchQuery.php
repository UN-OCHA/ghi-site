<?php

namespace Drupal\ghi_plans\Query;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ghi_plans\Helpers\AttachmentHelper;
use Drupal\ghi_plans\Traits\AttachmentFilterTrait;
use GuzzleHttp\ClientInterface;
use Drupal\hpc_api\Query\EndpointQuery;

/**
 * Query class for searching attachments.
 */
class AttachmentSearchQuery extends EndpointQuery {

  use AttachmentFilterTrait;

  /**
   * Constructs a new AttachmentQuery object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, CacheBackendInterface $cache, KillSwitch $kill_switch, ClientInterface $http_client, AccountProxyInterface $user) {
    parent::__construct($config_factory, $logger_factory, $cache, $kill_switch, $http_client, $user);

    $this->endpointUrl = 'public/attachment';
    // @codingStandardsIgnoreStart
    // @todo Implement this once HID login has been added.
    // if ($this->user->isAuthenticated()) {
    //   $this->endpointUrl = 'attachment';
    // }
    // @codingStandardsIgnoreEnd
    $this->endpointVersion = 'v2';
    $this->endpointArgs = [
      'disaggregation' => 'false',
      'version' => 'current',
    ];
  }

  /**
   * Get attachments by object type and id, optionally filtered.
   *
   * @param string $object_type
   *   The object type for an attachment, either "governingEntity" or
   *   "planEntity".
   * @param array|int $object_ids
   *   The object ids that the attachments should belong to.
   * @param array $filter
   *   An optional filter array, e.g.:
   *   [
   *     'type' => 'caseload',
   *   ].
   *
   * @return array
   *   The matching and processed attachment objects.
   */
  public function getAttachmentsByObject($object_type, $object_ids, array $filter = NULL) {
    $this->setEndpointArguments([
      'objectType' => $object_type,
      'objectIds' => implode(',', (array) $object_ids),
    ]);
    $attachments = $this->getData();

    if (empty($attachments)) {
      return NULL;
    }

    if (is_array($filter)) {
      $attachments = $this->filterAttachments($attachments, $filter);
      if (empty($attachments)) {
        return NULL;
      }
    }

    return array_map(function ($attachment) {
      return AttachmentHelper::processAttachment($attachment);
    }, $attachments);
  }

}
