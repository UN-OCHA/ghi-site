<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

/**
 * Abstraction for API caseload attachment objects.
 */
class CaseloadAttachment extends Attachment implements CaseloadAttachmentInterface {

  /**
   * {@inheritdoc}
   */
  public function getCaseloadValue($metric_type, $metric_name = NULL): ?float {
    $values = $this->getCurrentValues();
    return $values[$metric_type] ?? NULL;
  }

}
