<?php

namespace Drupal\ghi_plans\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'ghi_plans_linked_plans' field type.
 *
 * @FieldType(
 *   id = "ghi_plans_linked_plans",
 *   label = @Translation("Linked Plans"),
 *   category = @Translation("GHI Fields"),
 *   default_widget = "ghi_plans_linked_plans",
 *   default_formatter = "ghi_plans_linked_plans"
 * )
 */
class GlobalPlanLinkedPlansItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'linked_plan' => [
          'type' => 'int',
          'unsigned' => TRUE,
        ],
        'requirements_override' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'size' => 'big',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {

    $properties['linked_plan'] = DataDefinition::create('integer')
      ->setLabel(t('Linked Plan'));
    $properties['requirements_override'] = DataDefinition::create('integer')
      ->setLabel(t('Requirements override'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $linked_plan = $this->get('linked_plan')->getValue();
    $requirements_override = $this->get('requirements_override')->getValue();
    return empty($linked_plan) && empty($requirements_override);
  }

}
