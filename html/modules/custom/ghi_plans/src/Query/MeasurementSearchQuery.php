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
 * Query class for searching measurements.
 */
class MeasurementSearchQuery extends EndpointQuery {

  /**
   * Constructs a new AttachmentQuery object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, CacheBackendInterface $cache, KillSwitch $kill_switch, ClientInterface $http_client, AccountProxyInterface $user) {
    parent::__construct($config_factory, $logger_factory, $cache, $kill_switch, $http_client, $user);

    if ($user->isAuthenticated()) {
      $this->endpointUrl = 'measurement';
    }
    else {
      $this->endpointUrl = 'public/attachment';
    }

    // @codingStandardsIgnoreStart
    // @todo Implement this once HID login has been added.
    // if ($this->user->isAuthenticated()) {
    //   $this->endpointUrl = 'attachment';
    // }
    // @codingStandardsIgnoreEnd
    $this->endpointVersion = 'v2';
  }

  /**
   * Get measurements by object type and id, optionally filtered.
   *
   * @param int|array $attachment_id
   *   The attachment id(s) to fetch measurements for.
   * @param array $filter
   *   An optional filter array, e.g.:
   *   [
   *     'type' => 'caseload',
   *   ].
   *
   * @return array
   *   The matching and processed attachment objects.
   */
  public function getMeasurementsForAttachments($attachment_id, array $filter = NULL) {
    $this->setEndpointArguments([
      'attachmentId' => implode(',', (array) $attachment_id),
    ]);
    $measurements = $this->getData();

    if (empty($measurements)) {
      return NULL;
    }

    if (is_array($filter)) {
      $measurements = $this->filterAttachments($measurements, $filter);
      if (empty($measurements)) {
        return NULL;
      }
    }
    return $measurements;
  }

  /**
   * Get measurements by object type and id, optionally filtered.
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
  public function getMeasurementsByObject($object_type, $object_ids, array $filter = NULL) {
    $this->setEndpointArguments([
      'objectType' => $object_type,
      'objectIds' => implode(',', (array) $object_ids),
    ]);
    $measurements = $this->getData();

    if (empty($measurements)) {
      return NULL;
    }

    if (is_array($filter)) {
      $measurements = $this->filterAttachments($measurements, $filter);
      if (empty($measurements)) {
        return NULL;
      }
    }
    return $measurements;
  }

}
