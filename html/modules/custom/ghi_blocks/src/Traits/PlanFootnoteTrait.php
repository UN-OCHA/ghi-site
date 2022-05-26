<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\hpc_common\Helpers\ThemeHelper;

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

  /**
   * Build a footnote as a tooltip.
   *
   * @param object $footnotes
   *   A footnotes object.
   * @param string $property
   *   The property for which to retrieve the footnote.
   *
   * @return array|null
   *   A render array for the footnote tooltip.
   */
  public function buildFootnoteTooltip($footnotes, $property) {
    if (!is_object($footnotes)) {
      return NULL;
    }
    if (!property_exists($footnotes, $property) || empty($footnotes->$property)) {
      return NULL;
    }
    return [
      '#theme' => 'hpc_tooltip',
      '#tooltip' => [
        '#plain_text' => $footnotes->$property,
      ],
    ];
  }

  /**
   * Get a rendered footnote as a tooltip.
   *
   * @param object $footnotes
   *   A footnotes object.
   * @param string $property
   *   The property for which to retrieve the footnote.
   *
   * @return string|null
   *   The fully rendered footnote tooltip.
   */
  public function getRenderedFootnoteTooltip($footnotes, $property) {
    $build = $this->buildFootnoteTooltip($footnotes, $property);
    return ThemeHelper::render($build, FALSE);
  }

}
