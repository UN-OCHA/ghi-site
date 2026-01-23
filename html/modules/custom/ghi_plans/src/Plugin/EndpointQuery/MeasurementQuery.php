<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for attachments.
 *
 * @EndpointQuery(
 *   id = "measurement_query",
 *   label = @Translation("Measurement query"),
 *   endpoint = {
 *     "public" = "public/attachment/{attachment_id}",
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
   * Get the unprocessed measurements for the given attachment.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment
   *   The attachment to query.
   * @param bool $disaggregation
   *   Whether to fetch disaggregation data or not.
   *
   * @return array
   *   An array of unprocessed measurement objects.
   *
   * @todo Port to fabric.
   */
  public function getUnprocessedMeasurements(DataAttachment $attachment, $disaggregation = FALSE) {
    // phpcs:disable
    // $endpoint_args = [];
    // if (!$disaggregation) {
    //   $endpoint_args['disaggregation'] = 'false';
    // }
    // $plan_id = $attachment->getPlanId();
    // if ($plan_id) {
    //   $this->setCacheTags([
    //     'plan_id:' . $plan_id,
    //   ]);
    // }
    // $data = $this->getData(['attachment_id' => $attachment->id()], $endpoint_args);
    // return $data->measurements ?? [];
    // phpcs:enable
    return [];
  }

}
