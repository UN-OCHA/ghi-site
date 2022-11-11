<?php

namespace Drupal\ghi_content\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'ghi_remote_article' field type.
 *
 * @FieldType(
 *   id = "ghi_remote_article",
 *   label = @Translation("Remote article"),
 *   category = @Translation("GHI Fields"),
 *   default_widget = "ghi_remote_article",
 *   default_formatter = "ghi_remote_article",
 *   cardinality = 1
 * )
 */
class ArticleItem extends FieldItemBase {

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
        'article_id' => [
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
    $properties['article_id'] = DataDefinition::create('integer')
      ->setLabel(t('ID'));
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $remote_source = $this->get('remote_source')->getValue();
    $article_id = $this->get('article_id')->getValue();
    return empty($remote_source) || empty($article_id);
  }

}
