<?php

namespace Drupal\ghi_plans\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\hpc_common\Helpers\NodeHelper;

/**
 * Defines the 'ghi_plans_linked_plans' field widget.
 *
 * @FieldWidget(
 *   id = "ghi_plans_linked_plans",
 *   label = @Translation("Linked Plans"),
 *   field_types = {"ghi_plans_linked_plans"},
 * )
 */
class GlobalPlanLinkedPlansWidget extends WidgetBase {

  /**
   * A wrapper id for ajax replace actions.
   *
   * @var string
   */
  protected $ajaxWrapperId;

  /**
   * Special handling to create form elements for multiple values.
   *
   * We finally call the parent method, but need this override to handle item
   * removal properly. Some things we do here:
   * - remove items
   * - overwrite the user input to prevent stale data after item removal
   * - re-setting the weight per item to be sure that empty elements don't end
   *   up on top after removing an item, which changes the max amount, which
   *   in turn changes the weight options
   * - add a common ajax wrapper for the remove buttons and the add more button
   * That's all.
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $parents = $form['#parents'];
    $field_parents = array_merge($parents, [$field_name]);
    $id_prefix = implode('-', $field_parents);
    $this->ajaxWrapperId = Html::getUniqueId($id_prefix . '-ajax-wrapper');

    if ($form_state->has('remove') && $form_state->get('remove') !== NULL) {
      $delta = $form_state->get('remove');

      // Check if this offset actually exists in the item list. We also support
      // removing empty items that have been added after clicking on the
      // "Add more" button.
      if ($items->offsetExists($delta)) {
        $items->removeItem($delta);
      }
      $form_state->set('remove', NULL);

      // Overwrite the user input. Awkward as it seems, but it seem necessary
      // so that stale data after removing an element is not used to populate
      // the input fields.
      $input = $form_state->getUserInput();
      $field_input = NestedArray::getValue($input, $field_parents);
      unset($field_input[$delta]);
      NestedArray::setValue($input, $field_parents, array_values($field_input));
      $form_state->setUserInput($input);
    }

    // Call original method of WidgetBase to let it handle the element
    // generation.
    $elements = parent::formMultipleElements($items, $form, $form_state);

    foreach (Element::children($elements) as $key => $element_key) {
      if (!is_int($element_key) || !is_array($elements[$element_key]) || !array_key_exists('_weight', $elements[$element_key])) {
        continue;
      }
      $elements[$element_key]['_weight']['#default_value'] = $key - $elements[$element_key]['_weight']['#delta'];
    }

    // Add a wrapper for ajax actions.
    $elements['#prefix'] = '<div id="' . $this->ajaxWrapperId . '">';
    $elements['#suffix'] = '</div>';
    $elements['add_more']['#ajax']['wrapper'] = $this->ajaxWrapperId;

    return $elements;
  }

  /**
   * Get the field state.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   Array with the field state, see self::getWidgetState().
   */
  private function getFieldState(array $form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $parents = $form['#parents'];
    return static::getWidgetState($parents, $field_name, $form_state);
  }

  /**
   * Get the max item count.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return int
   *   The max number of items.
   */
  private function getMaxItemCount(array $form, FormStateInterface $form_state) {
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();

    // Determine the number of widgets to display.
    switch ($cardinality) {
      case FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED:
        $field_state = $this->getFieldState($form, $form_state);
        $max = $field_state['items_count'];
        break;

      default:
        $max = $cardinality - 1;
        break;
    }
    return $max;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $element['linked_plan'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Linked Plan'),
      '#autocomplete_route_name' => 'ghi_plans.plan_autocomplete',
      '#size' => 20,
      '#default_value' => isset($items[$delta]->linked_plan) ? NodeHelper::getTitleFromOriginalId($items[$delta]->linked_plan, 'plan') : '',
    ];

    $element['requirements_override'] = [
      '#type' => 'number',
      '#title' => $this->t('Requirements override'),
      '#min' => 0,
      '#default_value' => isset($items[$delta]->requirements_override) ? $items[$delta]->requirements_override : '',
    ];

    if ($delta < $this->getMaxItemCount($form, $form_state)) {
      $element['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#submit' => [get_class($this) . '::removeSubmit'],
        '#name' => 'remove_' . $delta,
        '#delta' => $delta,
        '#ajax' => [
          'callback' => [$this, 'removeCallback'],
          'wrapper' => $this->ajaxWrapperId,
        ],
      ];
    }

    $element['#theme_wrappers'] = ['container', 'form_element'];
    $element['#attributes']['class'][] = 'container-inline';
    $element['#attributes']['class'][] = 'ghi-plans-linked-plans';

    return $element;
  }

  /**
   * Callback for ajax-enabled buttons.
   */
  public function removeCallback(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, [reset($triggering_element['#parents'])]);
    return $element;
  }

  /**
   * Submit handler for the "remove one" button.
   *
   * Decrements the max counter and causes a form rebuild.
   */
  public function removeSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $form_state->set('remove', $triggering_element['#delta']);

    // Go one level up in the form, to the widgets container.
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -2));
    $field_name = $element['#field_name'];
    $parents = $element['#field_parents'];

    // Increment the items count.
    $field_state = static::getWidgetState($parents, $field_name, $form_state);
    $field_state['items_count']--;
    static::setWidgetState($parents, $field_name, $form_state, $field_state);

    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {

    foreach ($values as $delta => $value) {
      if (empty($value['linked_plan'])) {
        $values[$delta]['linked_plan'] = NULL;
      }
      if (empty($value['requirements_override'])) {
        $values[$delta]['requirements_override'] = NULL;
      }

      if (empty($value['linked_plan']) && empty($value['requirements_override'])) {
        $values[$delta]['linked_plan'] = NULL;
        $values[$delta]['requirements_override'] = NULL;
      }

      // We need to store the original id as the field value.
      if (!empty($value['linked_plan'])) {
        $values[$delta]['linked_plan'] = (int) NodeHelper::getOriginalIdFromTitle($value['linked_plan'], 'plan');
      }
    }

    return $values;
  }

}
