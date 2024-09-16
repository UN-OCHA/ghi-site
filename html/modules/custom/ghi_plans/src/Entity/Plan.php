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
      $this->getPlanSubtitle(),
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
   * @return \Drupal\taxonomy\TermInterface|null
   *   The plan type.
   */
  public function getPlanType() {
    if (!$this->hasField('field_plan_type')) {
      return NULL;
    }
    return $this->get('field_plan_type')?->entity ?? NULL;
  }

  /**
   * Get the plan subtitle.
   *
   * @return string|null
   *   The plan subtitle.
   */
  public function getPlanSubtitle() {
    if (!$this->hasField('field_subtitle')) {
      return NULL;
    }
    return $this->get('field_subtitle')->value;
  }

  /**
   * Get the short version of the plan type label.
   *
   * @param bool $override
   *   Whether the overridden label can be used if it's available.
   *
   * @return string|null
   *   The short label of the plan type.
   */
  public function getPlanTypeShortLabel($override = TRUE) {
    $plan_type = $this->getPlanType();
    $included_in_totals = $plan_type ? $plan_type->get('field_included_in_totals')->value : FALSE;
    $plan_type_label = $override ? $this->getPlanSubtitle() : $plan_type->label();
    return $plan_type_label ? $this->getPlanTypeShortName($plan_type_label, $included_in_totals) : NULL;
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
   * Get the operations category.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   The operations category term or NULL if not set.
   */
  public function getOperationsCategory() {
    if (!$this->hasField('field_operations_category')) {
      return NULL;
    }
    return $this->get('field_operations_category')?->entity ?: NULL;
  }

  /**
   * Get the configured plan caseload.
   *
   * @return int|null
   *   The ID of the configured plan caseload.
   */
  public function getPlanCaseloadId() {
    if (!$this->hasField('field_plan_caseload')) {
      return NULL;
    }
    return $this->get('field_plan_caseload')?->attachment_id ?: NULL;
  }

  /**
   * Get the document uri.
   *
   * @return string|null
   *   A uri to the document for the plan.
   */
  public function getDocumentUri() {
    if (!$this->hasField('field_plan_document_link')) {
      return NULL;
    }
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
   * @return int|null
   *   The highest supported admin level.
   */
  public function getMaxAdminLevel() {
    if (!$this->hasField('field_max_admin_level')) {
      return NULL;
    }
    return $this->get('field_max_admin_level')->value ?? NULL;
  }

  /**
   * Whether this plan can link financial data to FTS.
   *
   * @return bool
   *   Whether the plan can be linked to FTS.
   */
  public function canLinkToFts() {
    if (!$this->hasField('field_link_to_fts')) {
      return FALSE;
    }
    return $this->get('field_link_to_fts')->value ?? FALSE;
  }

}
