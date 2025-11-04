<?php

namespace Drupal\ghi_plans\ApiObjects\Partials;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;
use Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachmentInterface;
use Drupal\ghi_plans\Traits\PartialCaseloadTrait;

/**
 * Abstraction class for a plan partial object.
 *
 * This kind of partial object is a stripped-down, limited-data, object that
 * appears in some specific endpoints. We map this here to provide type hinting
 * and abstracted data access.
 */
class PlanOverviewCaseload extends BaseObject implements CaseloadAttachmentInterface {

  use PartialCaseloadTrait;

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $data = $this->getRawData();

    $calculated_fields = $data->calculatedFields ?? [];
    if ($calculated_fields && !is_array($calculated_fields)) {
      $calculated_fields = [
        $calculated_fields->type => $calculated_fields,
      ];
    }
    $fields = array_merge($data->totals, $calculated_fields);
    return (object) [
      'id' => $data->attachmentId,
      'custom_id' => $data->customReference,
      'original_fields' => $fields,
      'original_field_types' => array_map(function ($item) {
        return $item->type;
      }, $fields),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomId(): string {
    return $this->custom_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalFields(): array {
    return $this->original_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalFieldTypes(): array {
    return $this->original_field_types;
  }

}
