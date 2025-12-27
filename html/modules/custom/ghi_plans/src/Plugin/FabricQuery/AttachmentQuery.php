<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'attachment' fabric query.
 */
#[FabricQuery(
  id: 'attachment',
  label: new TranslatableMarkup('Attachment query'),
)]
class AttachmentQuery extends FabricQueryBase {

  /**
   * Get an attachment by its id.
   *
   * @param int $attachment_id
   *   The attachment id.
   * @param string|int $reporting_period
   *   The reporting period for which to load the attachment data.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface|null
   *   The attachment object or NULL if not found.
   */
  public function getAttachment(int $attachment_id, $reporting_period = 'latest'): ?AttachmentInterface {
    // Get the attachment data.
    $payload = "
      attachmentFacts (filter: {
        IsTotal: { eq: 1 }
        HpcId:  {
          eq: {$attachment_id}
        }
      }) {
        items {
          Id
          AttachmentId
          MetricTypeId
          PeriodId
          SectorId
          LocationId
          GenderId
          AgeGroupId
          PopulationStatusId
          SettlementTypeId
          DisabilityStatusId
          HealthInterventionCategoryId
          MaternalStatusId
          DisaggregationCategoryOtherId
          DeliveryModalityId
          CalcMethodId
          IsTotal
          ValueNum
          CustomMetricName
          EffectiveFrom
          EffectiveTo
          Description
          VisibilityGroupId
          Source
          SourceId
          CreatedAt
          UpdatedAt
          IsLocked
          HpcId
        }
      }";
    $facts_data = $this->fabricQuery->query($payload);
    $facts = $this->getItems($facts_data, 'attachmentFacts');
    if (empty($facts)) {
      return NULL;
    }
    $fact = reset($facts);

    $attachment = NULL;
    if (!empty($fact->AttachmentId)) {
      $payload = "
        attachments (filter: {
          HpcId:  {
            eq: {$fact->AttachmentId}
          }
        }) {
          items {
            Id
            Name
            Description
            UnitId
            CalculationMethodId
            GoalPeriodStart
            GoalPeriodEnd
          }
        }";
      $data = $this->fabricQuery->query($payload);
      $attachments = $this->getItems($data, 'attachments');
      // Retrieving an attachment by id should yield a max of 1, so let's assert
      // that.
      assert(count($attachments) <= 1);
      $attachment = reset($attachments);
    }
    if (empty($attachment)) {
      return NULL;
    }

    // @todo Turn this into an actual Attachment api object.
    return NULL;
  }

}
