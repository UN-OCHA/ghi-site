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
      facts (filter: {
        IsTotal: { eq: 1 }
        HpcId:  {
          eq: {$attachment_id}
        }
      }) {
        items {
          HpcId
          PlanId
          PeriodId
          LogframeEntityId
          CoordinationEntityId
          IndicatorId
          UnitId
          MetricTypeId
          IsTotal
          FactType
          FactScope
          AttachmentCustomReference
          ValueNum
          Description
          VisibilityGroupId
          SourceType
          Source
          SourceId
        }
      }";
    $facts_data = $this->fabricQuery->query($payload);
    $facts = $this->getItems($facts_data, 'facts');
    if (empty($facts)) {
      return NULL;
    }
    $fact = reset($facts);

    $attachment = NULL;
    if (!empty($fact->IndicatorId)) {
      $payload = "
        indicators (filter: {
          HpcId:  {
            eq: {$fact->IndicatorId}
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
      $indicators = $this->getItems($data, 'indicators');
      // Retrieving an indicator by id should yield a max of 1, so let's assert
      // that.
      assert(count($indicators) <= 1);
      $attachment = reset($indicators);
    }
    if (empty($attachment)) {
      return NULL;
    }

    // @todo Turn this into an actual Attachment api object.
    return NULL;
  }

}
