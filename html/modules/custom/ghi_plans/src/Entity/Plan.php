<?php

namespace Drupal\ghi_plans\Entity;

use Drupal\ghi_base_objects\Entity\BaseObject;

/**
 * Bundle class for plan base objects.
 */
class Plan extends BaseObject {

  /**
   * Get the plan status label.
   *
   * @return string|null
   *   A label for the plan status or NULL if the field is not found.
   */
  public function getPlanStatusLabel() {
    $plan_status = $this->get('field_plan_status') ?? NULL;
    if (!$plan_status) {
      return NULL;
    }
    $field_definition = $plan_status->getFieldDefinition();
    return $plan_status->value ? $field_definition->getSetting('on_label') : $field_definition->getSetting('off_label');
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
    return $this->get('field_decimal_format')->value ?? NULL;
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

  /**
   * Map a status string value to a boolean.
   *
   * This basically maps "published" to TRUE and everything else to FALSE.
   *
   * @param string $value
   *   The incoming value.
   *
   * @return bool
   *   The resulting status.
   */
  public static function mapPlanStatus($value) {
    if (strtolower($value) == 'published') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Map the prevent_fts_link value (Insight) to the link_to_fts value (GHI).
   *
   * We negate the value because we try to prevent negative field meanings.
   *
   * @param bool $value
   *   The incoming value.
   *
   * @return bool
   *   The resulting status.
   */
  public static function mapFtsLink($value) {
    return !$value;
  }

}
