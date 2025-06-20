<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ghi_plans\Helpers\AttachmentHelper;
use Drupal\ghi_plans\Traits\PlanVersionArgument;
use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for attachments.
 *
 * @EndpointQuery(
 *   id = "attachment_query",
 *   label = @Translation("Attachment query"),
 *   endpoint = {
 *     "public" = "public/attachment/{attachment_id}",
 *     "authenticated" = "attachment/{attachment_id}",
 *     "version" = "v2",
 *     "query" = {
 *       "version" = "current",
 *       "disaggregation" = "false",
 *     }
 *   }
 * )
 */
class AttachmentQuery extends EndpointQueryBase implements ContainerFactoryPluginInterface {

  use PlanVersionArgument;

  /**
   * {@inheritdoc}
   */
  public function getData(array $placeholders = [], array $query_args = []) {
    $this->endpointQuery->setPlaceholders($placeholders);
    if ($plan_id = $this->getPlaceholder('plan_id')) {
      $query_args['version'] = $this->getPlanVersionArgumentForPlanId($plan_id);
    }
    return parent::getData($placeholders, $query_args);
  }

  /**
   * Get an attachment by id.
   *
   * @param int $attachment_id
   *   The attachment id to query.
   * @param bool $disaggregated
   *   Whether to fetch disaggregated data directly.
   * @param string|int $reporting_period
   *   The reporting period for which to load the attachment data.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface
   *   The processed attachment object.
   */
  public function getAttachment($attachment_id, $disaggregated = FALSE, $reporting_period = 'latest') {
    if (is_string($attachment_id) && strpos($attachment_id, 'group_') === 0) {
      return NULL;
    }
    if ($disaggregated) {
      $data = $this->getAttachmentDataWithDisaggregatedData($attachment_id, $reporting_period);
    }
    else {
      $data = $this->getData([
        'attachment_id' => $attachment_id,
      ], [
        'reporting_period' => $reporting_period,
      ]);
    }
    if (empty($data)) {
      return NULL;
    }

    return AttachmentHelper::processAttachment((object) $data);
  }

  /**
   * Get an attachment by id with the full dissagregated data.
   *
   * @param int $attachment_id
   *   The attachment id to query.
   * @param string|int $reporting_period
   *   The reporting period for which to load the attachment data.
   *
   * @return object
   *   The raw attachment object from the API.
   */
  public function getAttachmentDataWithDisaggregatedData($attachment_id, $reporting_period = 'latest') {
    $data = $this->getData(['attachment_id' => $attachment_id], [
      'disaggregation' => 'true',
      'reporting_period' => $reporting_period,
    ]);
    if (empty($data)) {
      return NULL;
    }
    return $data;
  }

}
