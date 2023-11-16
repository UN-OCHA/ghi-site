<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\ghi_plans\Traits\PlanVersionArgument;
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

  use PlanVersionArgument;

  /**
   * Get the unprocessed measurements for the given attachment.
   *
   * @param Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment
   *   The attachment to query.
   * @param bool $disaggregation
   *   Whether to fetch disaggregation data or not.
   *
   * @return array
   *   An array of unprocessed measurement objects.
   */
  public function getUnprocessedMeasurements(DataAttachment $attachment, $disaggregation = FALSE) {
    $endpoint_args = [];
    if (!$disaggregation) {
      $endpoint_args['disaggregation'] = 'false';
    }
    if ($this->isAutenticatedEndpoint) {
      if ($plan_id = $attachment->getPlanId()) {
        $endpoint_args['version'] = $this->getPlanVersionArgumentForPlanId($plan_id);
      }
      $data = $this->getData([], ['attachmentId' => $attachment->id()] + $endpoint_args);
      return $data;
    }
    else {
      $data = $this->getData(['attachment_id' => $attachment->id()], $endpoint_args);
      return $data->measurements ?? [];
    }
  }

}
