<?php

namespace Drupal\ghi_plans\ApiObjects\Partials;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;
use Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachmentInterface;

/**
 * Abstraction class for a plan partial object.
 *
 * This kind of partial object is a stripped-down, limited-data, object that
 * appears in some specific endpoints. We map this here to provide type hinting
 * and abstracted data access.
 */
class PlanOverviewCaseload extends BaseObject implements CaseloadAttachmentInterface {

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $data = $this->getRawData();

    $calculated_fields = $data->calculatedFields ?? [];
    if ($calculated_fields && !is_array($calculated_fields) && is_object($calculated_fields)) {
      $calculated_fields = [$calculated_fields];
    }
    $fields = array_merge($data->totals, $calculated_fields);
    return (object) [
      'id' => $data->Id,
      'custom_id' => $data->CustomReference,
      'plan_id' => $data->PlanId,
      'fields' => $fields,
      'field_types' => array_map(function ($item) {
        return $item->type;
      }, $fields),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomId() {
    return $this->custom_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomIdWithRefCode(): string {
    return 'PL' . $this->getCustomId();
  }

  /**
   * {@inheritdoc}
   */
  public function getComposedReference(): string {
    return $this->getCustomIdWithRefCode();
  }

  /**
   * {@inheritdoc}
   */
  public function getFields() {
    return $this->fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldTypes() {
    return $this->field_types;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlanId() {
    return $this->plan_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntityType() {
    return 'plan';
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntityId() {
    return $this->getPlanId();
  }

}
