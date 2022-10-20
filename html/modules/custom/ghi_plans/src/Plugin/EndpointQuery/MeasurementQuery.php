<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for attachments.
 *
 * @EndpointQuery(
 *   id = "measurement_query",
 *   label = @Translation("Measurement query"),
 *   endpoint = {
 *     "public" = "public/attachment/{attachment_id}",
 *     "authenticated" = "measurement",
 *     "version" = "v2",
 *     "query" = {
 *       "version" = "current",
 *       "disaggregation" = "false",
 *     }
 *   }
 * )
 */
class MeasurementQuery extends EndpointQueryBase implements ContainerFactoryPluginInterface {

  /**
   * Get an attachment by id.
   *
   * @param int $attachment_id
   *   The attachment id to query.
   * @param bool $disaggregation
   *   Whether to fetch disaggregation data or not.
   *
   * @return array
   *   An array of unprocessed measurement objects.
   */
  public function getUnprocessedMeasurements($attachment_id, $disaggregation = FALSE) {
    if (is_string($attachment_id) && strpos($attachment_id, 'group_') === 0) {
      return [];
    }
    $endpoint_args = [];
    if (!$disaggregation) {
      $endpoint_args['disaggregation'] = 'false';
    }
    if ($this->isAutenticatedEndpoint) {
      $data = $this->getData([], ['attachmentId' => $attachment_id] + $endpoint_args);
      return $data;
    }
    else {
      $data = $this->getData(['attachment_id' => $attachment_id], $endpoint_args);
      return $data->measurements ?? [];
    }
  }

}
