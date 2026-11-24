<?php

namespace Drupal\ghi_base_objects\Plugin\migrate\destination;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\migrate\Attribute\MigrateDestination;
use Drupal\migrate\Plugin\migrate\destination\EntityContentBase;
use Drupal\migrate\Row;

/**
 * Saves migrated base objects only when their stored values have changed.
 */
#[MigrateDestination(self::PLUGIN_ID)]
class BaseObjectSkipUnchanged extends EntityContentBase {

  public const PLUGIN_ID = 'base_object_skip_unchanged';

  /**
   * {@inheritdoc}
   */
  protected static function getEntityTypeId($plugin_id) {
    return 'base_object';
  }

  /**
   * {@inheritdoc}
   */
  protected function updateEntity(EntityInterface $entity, Row $row) {
    // Reuse the loaded entity for the later change comparison instead of
    // loading the unchanged entity again for every migration row.
    $entity->setOriginal(clone $entity);
    return parent::updateEntity($entity, $row);
  }

  /**
   * {@inheritdoc}
   */
  protected function save(ContentEntityInterface $entity, array $old_destination_id_values = []) {
    if (!$entity->isNew() && !$entity->hasTranslationChanges()) {
      return [$entity->id()];
    }
    return parent::save($entity, $old_destination_id_values);
  }

}
