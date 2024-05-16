<?php

namespace Drupal\ghi_plans\Entity;

use Drupal\ghi_base_objects\Entity\BaseObject;
use Drupal\ghi_base_objects\Entity\BaseObjectMetaDataInterface;
use Drupal\ghi_plans\Traits\PlanTypeTrait;

/**
 * Bundle class for plan base objects.
 */
class Plan extends BaseObject implements BaseObjectMetaDataInterface {

  use PlanTypeTrait;

  /**
   * {@inheritdoc}
   */
  public function getPageTitleMetaData() {
    return array_filter([
      $this->getPlanTypeLabel(),
      $this->getPlanStatusLabel(),
    ]);
  }

  /**
   * Get the plan year.
   *
   * @return string
   *   The plan year.
   */
  public function getYear() {
    return $this->get('field_year')->value;
  }

  /**
   * Get the plan type.
   *
   * @param bool $override
   *   Whether the overridden label can be used if it's available.
   *
   * @return string
   *   The label of the plan type.
   */
  public function getPlanTypeLabel($override = TRUE) {
    if (!$this->hasField('field_plan_type_label_override')) {
      return NULL;
    }
    $plan_type_label_override = $this->get('field_plan_type_label_override')->value;
    if ($override && !empty($plan_type_label_override)) {
      return $plan_type_label_override;
    }
    $plan_type = $this->get('field_plan_type')?->entity ?? NULL;
    return $plan_type ? $plan_type->label() : NULL;
  }

  /**
   * Get the short version of the plan type label.
   *
   * @param bool $override
   *   Whether the overridden label can be used if it's available.
   *
   * @return string
   *   The short label of the plan type.
   */
  public function getPlanTypeShortLabel($override = TRUE) {
    $plan_type = $this->get('field_plan_type')?->entity ?? NULL;
    $included_in_totals = $plan_type ? $plan_type->get('field_included_in_totals')->value : FALSE;
    return $this->getPlanTypeShortName($this->getPlanTypeLabel($override), $included_in_totals);
  }

  /**
   * Get the plan status label.
   *
   * @return string|null
   *   A label for the plan status or NULL if the field is not found.
   */
  public function getPlanStatusLabel() {
    if (!$this->hasField('field_plan_status')) {
      return NULL;
    }
    $plan_status = $this->get('field_plan_status') ?? NULL;
    if (!$plan_status) {
      return NULL;
    }
    $field_definition = $plan_status->getFieldDefinition();
    return $plan_status->value ? $field_definition->getSetting('on_label') : $field_definition->getSetting('off_label');
  }

  /**
   * Get the configured plan caseload.
   *
   * @return int|null
   *   The ID of the configured plan caseload.
   */
  public function getPlanCaseloadId() {
    $plan_caseload_id = $this->get('field_plan_caseload')?->attachment_id;
    return !empty($plan_caseload_id) ? $plan_caseload_id : NULL;
  }

  /**
   * Get the document uri.
   *
   * @return string|null
   *   A uri to the document for the plan.
   */
  public function getDocumentUri() {
    return $this->get('field_plan_document_link')->uri ?? NULL;
  }

  /**
   * Get the decimal format to use for number formatting.
   *
   * @return string|null
   *   Either 'comma', 'point' or NULL.
   */
  public function getDecimalFormat() {
    if (!$this->hasField('field_decimal_format')) {
      return NULL;
    }
    return $this->get('field_decimal_format')->value ?? NULL;
  }

  /**
   * Get the maximum admin level for the plan.
   *
   * @return int
   *   The highest supported admin level.
   */
  public function getMaxAdminLevel() {
    return $this->get('field_max_admin_level')->value;
  }

  /**
   * Whether this plan can link financial data to FTS.
   *
   * @return bool
   *   Whether the plan can be linked to FTS.
   */
  public function canLinkToFts() {
    return $this->get('field_link_to_fts')->value ?? FALSE;
  }

}
