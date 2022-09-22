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
   * Get the decimal format to use for number formatting.
   *
   * @return string|null
   *   Either 'comma', 'point' or NULL.
   */
  public function getDecimalFormat() {
    return $this->get('field_decimal_format')->value ?? NULL;
  }

}
