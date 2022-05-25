<?php

namespace Drupal\ghi_base_objects\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\ghi_base_objects\Traits\FootnotePropertyTrait;

/**
 * Plugin implementation of the 'ghi_footnotes' field type.
 *
 * @FieldType(
 *   id = "ghi_footnotes",
 *   label = @Translation("Footnotes"),
 *   category = @Translation("GHI Fields"),
 *   default_widget = "ghi_footnotes",
 *   default_formatter = "ghi_footnotes"
 * )
 */
class FootnotesItem extends FieldItemBase {

  use FootnotePropertyTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'available_properties' => [],
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = [];
    $options = $this->getFootnotePropertyOptions();
    $element['available_properties'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Available properties'),
      '#options' => $options,
      '#default_value' => $this->getSetting('available_properties') ?? [],
      '#required' => TRUE,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'property' => [
          'description' => 'The property for which the footnote should be shown',
          'type' => 'varchar',
          'length' => 128,
          'not null' => TRUE,
        ],
        'footnote' => [
          'description' => 'The text to show for the footnote.',
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['property'] = DataDefinition::create('string')
      ->setLabel(t('Property'));
    $properties['footnote'] = DataDefinition::create('string')
      ->setLabel(t('Footnote'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $property = $this->get('property')->getValue();
    $footnote = $this->get('footnote')->getValue();
    return empty($property) || empty($footnote);
  }

}
