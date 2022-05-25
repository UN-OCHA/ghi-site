<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\ghi_base_objects\Entity\BaseObjectInterface;

/**
 * Helper trait for plan footnotes.
 */
trait PlanFootnoteTrait {

  /**
   * Get the footnotes for a plan.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $plan
   *   The plan base object.
   *
   * @return object|null
   *   An object with footnotes for the plan, or NULL.
   */
  public function getFootnotesForPlanBaseobject(BaseObjectInterface $plan) {
    $field = $plan->get('field_footnotes');
    if ($field->isEmpty()) {
      return NULL;
    }
    $field_definition = $field->getFieldDefinition();
    $available_properties = array_filter($field_definition->getSetting('available_properties'));
    $values = [];
    foreach ($field->getValue() as $value) {
      $values[$value['property']] = $value['footnote'];
    }
    $footnotes = [];
    foreach ($available_properties as $property) {
      $footnotes[$property] = $values[$property] ?? NULL;
    }
    return (object) $footnotes;
  }

}
