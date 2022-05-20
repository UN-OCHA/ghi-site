<?php

namespace Drupal\ghi_plans\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'ghi_plans_caseload' field type.
 *
 * @FieldType(
 *   id = "ghi_plans_plan_caseload",
 *   label = @Translation("Plan caseload"),
 *   category = @Translation("GHI Fields"),
 *   default_widget = "ghi_plans_plan_caseload",
 *   default_formatter = "ghi_plans_plan_caseload"
 * )
 */
class PlanCaseloadItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'attachment_id' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'size' => 'normal',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['attachment_id'] = DataDefinition::create('integer')
      ->setLabel(t('Attachment id'));
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return $this->get('attachment_id')->getValue() === NULL;
  }

}
