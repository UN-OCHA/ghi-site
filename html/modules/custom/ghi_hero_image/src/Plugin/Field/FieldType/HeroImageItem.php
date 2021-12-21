<?php

namespace Drupal\ghi_hero_image\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Plugin implementation of the 'ghi_hero_image' field type.
 *
 * @FieldType(
 *   id = "ghi_hero_image",
 *   label = @Translation("Hero image"),
 *   category = @Translation("GHI Fields"),
 *   default_widget = "ghi_hero_image",
 *   default_formatter = "ghi_hero_image",
 *   cardinality = 1
 * )
 */
class HeroImageItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'source' => [
          'type' => 'varchar',
          'length' => 128,
          'not null' => TRUE,
          'description' => 'The source identifier for the image storage',
        ],
        'settings' => [
          'type' => 'blob',
          'size' => 'big',
          'not null' => TRUE,
          'description' => 'The settings for the image storage.',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {

    $properties['source'] = DataDefinition::create('string')
      ->setLabel(t('Source'));
    $properties['settings'] = MapDataDefinition::create()
      ->setLabel(t('Settings'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $source = $this->get('source')->getValue();
    return empty($source);
  }

}
