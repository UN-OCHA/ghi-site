<?php

namespace Drupal\ghi_plans\EventSubscriber;

use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachmentInterface;
use Drupal\hpc_api\Event\EndpointDataEvent;
use Drupal\hpc_api\Query\EndpointQueryManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alter API data before it get's processed.
 */
class EndpointDataSubscriber implements EventSubscriberInterface {

  /**
   * The attachment query.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentQuery
   */
  public $attachmentQuery;

  /**
   * The plan query.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanBasicQuery
   */
  public $planQuery;

  /**
   * Constructs a new endpoint data event listener.
   */
  public function __construct(EndpointQueryManager $endpoint_query_manager) {
    $this->attachmentQuery = $endpoint_query_manager->createInstance('attachment_query');
    $this->planQuery = $endpoint_query_manager->createInstance('plan_basic_query');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EndpointDataEvent::class => 'alterEndpointData',
    ];
  }

  /**
   * Alter the raw data received from an API endpoint.
   *
   * @param \Drupal\hpc_api\Event\EndpointDataEvent $event
   *   The endpoint data event.
   */
  public function alterEndpointData(EndpointDataEvent $event) {
    $attachment_ids = &drupal_static(__FUNCTION__, []);
    $query = $event->getQuery();
    $data = $event->getData();
    if ($query->getEndpoint() == 'attachment/{attachment_id}' && !empty($data->measurements)) {
      $arguments = $query->getEndpointArguments() + $query->getPlaceholders();
      if (!empty($arguments['reporting_period']) && $arguments['reporting_period'] == 'all') {
        return;
      }

      if (empty($arguments['disaggregation']) || $arguments['disaggregation'] == 'false') {
        return;
      }
      $attachment_id = $arguments['attachment_id'];
      if (in_array($attachment_id, $attachment_ids)) {
        return;
      }
      $attachment_ids[] = $attachment_id;
      $attachment = $this->attachmentQuery->getAttachment($attachment_id);
      if (!$attachment instanceof DataAttachmentInterface) {
        return;
      }
      $plan_id = $attachment->getPlanId();
      if (!$plan_id) {
        return;
      }
      $plan = $this->planQuery->getBaseData($plan_id);
      if (!$plan) {
        return;
      }
      $last_period_id = $plan->getLastPublishedReportingPeriodId();
      if (!$last_period_id) {
        return;
      }

      // Remove all but the current reporting period.
      // @see https://humanitarian.atlassian.net/browse/HPC-10233
      foreach ($data->measurements as $key => $measurement) {
        if ($measurement->planReportingPeriodId != $last_period_id) {
          unset($data->measurements[$key]);
        }
      }
      $event->setData($data);
    }
  }

}
