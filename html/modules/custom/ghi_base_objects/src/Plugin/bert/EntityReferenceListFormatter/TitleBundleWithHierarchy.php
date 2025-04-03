<?php

namespace Drupal\ghi_base_objects\Plugin\bert\EntityReferenceListFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\bert\Plugin\bert\EntityReferenceListFormatter\TitleBundle;
use Drupal\ghi_base_objects\Entity\BaseObjectChildInterface;

/**
 * Displays the entity label and bundle.
 *
 * @EntityReferenceListFormatter(
 *   id = "title_bundle_with_hierarchy",
 *   label = @Translation("Entity title and bundle (with hierarchy)"),
 * )
 */
class TitleBundleWithHierarchy extends TitleBundle {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getCells(EntityInterface $entity): array {
    $cells = parent::getCells($entity);
    if ($entity instanceof BaseObjectChildInterface) {
      $cells[0]['#markup'] = $entity->labelWithParent();
    }
    return $cells;
  }

}
