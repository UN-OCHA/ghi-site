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
   *
   * @return array
   *   An array of unprocessed measurement objects.
   */
  public function getUnprocessedMeasurements($attachment_id) {
    if ($this->isAutenticatedEndpoint) {
      $data = $this->getData([], ['attachmentId' => $attachment_id]);
      return $data;
    }
    else {
      $data = $this->getData(['attachment_id' => $attachment_id]);
      return $data->measurements ?? [];
    }
  }

}
