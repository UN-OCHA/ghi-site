<?php

namespace Drupal\ghi_base_objects;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Base object entities.
 *
 * @ingroup ghi_base_objects
 */
class BaseObjectListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [];
    $header['id'] = $this->t('Base object ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\ghi_base_objects\Entity\BaseObject $entity */
    $row = [];
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.base_object.edit_form',
      ['base_object' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
