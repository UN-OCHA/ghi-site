<?php

namespace Drupal\ghi_plans\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'ghi_plans_linked_plans' formatter.
 *
 * @FieldFormatter(
 *   id = "ghi_plans_linked_plans",
 *   label = @Translation("Default"),
 *   field_types = {"ghi_plans_linked_plans"}
 * )
 */
class GlobalPlanLinkedPlansFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      if ($item->linked_plan) {
        $element[$delta]['linked_plan'] = [
          '#type' => 'item',
          '#title' => $this->t('Linked Plan'),
          '#markup' => $item->linked_plan,
        ];
      }

      if ($item->requirements_override) {
        $element[$delta]['requirements_override'] = [
          '#type' => 'item',
          '#title' => $this->t('Requirements override'),
          '#markup' => $item->requirements_override,
        ];
      }
    }

    return $element;
  }

}
