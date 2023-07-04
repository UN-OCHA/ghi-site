<?php

namespace Drupal\ghi_sections\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'section_menu' field type.
 *
 * @internal
 *   Plugin classes are internal.
 *
 * @FieldType(
 *   id = "section_menu",
 *   label = @Translation("Section Menu"),
 *   description = @Translation("Section Menu"),
 *   list_class = "\Drupal\ghi_sections\Field\SectionMenuItemList",
 *   no_ui = TRUE,
 *   cardinality = \Drupal\Core\Field\FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
 * )
 *
 * @property \Drupal\ghi_sections\Menu\SectionMenuItemInterface $menu_item
 */
class SectionMenuItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['menu_item'] = DataDefinition::create('section_menu')
      ->setLabel(new TranslatableMarkup('Section Menu'))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function __get($name) {
    // @todo \Drupal\Core\Field\FieldItemBase::__get() does not return default
    //   values for uninstantiated properties. This will forcibly instantiate
    //   all properties with the side-effect of a performance hit, resolve
    //   properly in https://www.drupal.org/node/2413471.
    $this->getProperties();
    return parent::__get($name);
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'menu_item';
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'columns' => [
        'menu_item' => [
          'type' => 'blob',
          'size' => 'normal',
          'serialize' => TRUE,
        ],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return empty($this->menu_item);
  }

}
