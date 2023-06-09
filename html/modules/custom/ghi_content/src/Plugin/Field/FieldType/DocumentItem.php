<?php

namespace Drupal\ghi_content\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'ghi_remote_document' field type.
 *
 * @FieldType(
 *   id = "ghi_remote_document",
 *   label = @Translation("Remote document"),
 *   category = @Translation("GHI Fields"),
 *   default_widget = "ghi_remote_document",
 *   default_formatter = "ghi_remote_document",
 *   cardinality = 1
 * )
 */
class DocumentItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'remote_source' => [
          'description' => 'The source identifier for the remote source',
          'type' => 'varchar',
          'length' => 128,
          'not null' => TRUE,
        ],
        'document_id' => [
          'description' => 'The ID of the remote content.',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];
    $properties['remote_source'] = DataDefinition::create('string')
      ->setLabel(t('Remote source'));
    $properties['document_id'] = DataDefinition::create('integer')
      ->setLabel(t('ID'));
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $remote_source = $this->get('remote_source')->getValue();
    $document_id = $this->get('document_id')->getValue();
    return empty($remote_source) || empty($document_id);
  }

}
