<?php

namespace Drupal\ghi_content\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Base class for plugin implementation of remote content field types.
 */
abstract class RemoteItemBase extends FieldItemBase {

  /**
   * Get the name of the column that holds the id.
   *
   * @return string
   *   The name of the id column.
   */
  public static function getIdColumnName() {
    return self::getContentType() . '_id';
  }

  /**
   * Get the content type that this item refers to.
   *
   * @return string
   *   The name of the content type.
   */
  public static function getContentType() {
    $path = explode('\\', get_called_class());
    $class = end($path);
    return strtolower(str_replace('Item', '', $class));
  }

  /**
   * Get the stored id of the content.
   *
   * @return int
   *   The id of the content.
   */
  public function getContentId() {
    return $this->{self::getIdColumnName()} ?? NULL;
  }

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
        self::getIdColumnName() => [
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
    $properties[self::getIdColumnName()] = DataDefinition::create('integer')
      ->setLabel(t('ID'));
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $remote_source = $this->get('remote_source')->getValue();
    $content_id = $this->get(self::getIdColumnName())->getValue();
    return empty($remote_source) || empty($content_id);
  }

}
