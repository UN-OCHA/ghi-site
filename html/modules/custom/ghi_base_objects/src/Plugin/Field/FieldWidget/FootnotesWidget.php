<?php

namespace Drupal\ghi_base_objects\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\ghi_base_objects\Traits\FootnotePropertyTrait;

/**
 * Defines the 'ghi_footnotes' field widget.
 *
 * @FieldWidget(
 *   id = "ghi_footnotes",
 *   label = @Translation("Footnotes"),
 *   field_types = {"ghi_footnotes"}
 * )
 */
class FootnotesWidget extends WidgetBase {

  use FootnotePropertyTrait;

  /**
   * {@inheritdoc}
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $title = $this->fieldDefinition->getLabel();
    $description = $this->getFilteredDescription();

    $parents = $form['#parents'];

    $elements = [
      '#type' => 'table',
      '#header' => [
        'property' => [
          'data' => 'property_key',
          'class' => 'visually-hidden',
          'hidden' => 'true',
          'aria-hidden' => 'true',
        ],
        'property_label' => $this->t('Property'),
        'footnote' => $this->t('Footnote'),
      ],
      '#rows' => [],
      '#field_name' => $field_name,
      '#required' => $this->fieldDefinition->isRequired(),
      '#title' => $title,
      '#description' => $description,
    ];

    // Property are coupled to the deltas for this field element.
    $property_options = $this->getFootnotePropertyOptions();
    $property_states = array_merge($property_options, $this->getFieldSetting('available_properties'));
    $property_keys = array_keys($property_states);

    $values = [];
    $item_count = $items->count();
    for ($delta = 0; $delta < $item_count; $delta++) {
      $values[$items[$delta]->property] = $items[$delta]->footnote;
    }

    $property_options_count = count($property_options);
    for ($delta = 0; $delta < $property_options_count; $delta++) {
      $property = $property_keys[$delta];
      if (empty($property_states[$property])) {
        continue;
      }
      // Add a new empty item if it doesn't exist yet at this delta.
      if (!isset($items[$delta])) {
        $item = $items->appendItem();
        $item->setValue([
          'property' => $property,
          'footnote' => '',
        ]);
        $items[$delta] = $item;
      }
      $items[$delta]->property = $property;
      $items[$delta]->footnote = $values[$property] ?? NULL;

      $element = $this->formElement($items, $delta, [], $form, $form_state);

      $column_keys = array_keys($elements['#header']);

      foreach (Element::children($element) as $field_key) {
        $field = $element[$field_key];
        $field_parents = array_merge($parents, [$field_name, $delta, $field_key]);
        $field['#title_display'] = 'none';
        $field['#title_display'] = 'none';
        $field['#name'] = array_shift($field_parents) . '[' . implode('][', $field_parents) . ']';
        $field['#parents'] = $field_parents;
        $field = in_array($field_key, $column_keys) ? ['data' => $field] : $field;
        if ($field_key == 'property') {
          $field += [
            'class' => 'visually-hidden',
            'hidden' => 'true',
            'aria-hidden' => 'true',
          ];
        }
        $elements['#rows'][$delta][$field_key] = $field;
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $options = $this->getFootnotePropertyOptions();
    $element['property'] = [
      '#type' => 'hidden',
      '#value' => $items[$delta]->property ?? '',
    ];
    $element['property_label'] = [
      '#markup' => $items[$delta]->property !== NULL ? $options[$items[$delta]->property] : NULL,
    ];
    $element['footnote'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Footnote'),
      '#value' => $items[$delta]->footnote ?? '',
      '#default_value' => $items[$delta]->footnote ?? '',
    ];
    return $element;
  }

}
