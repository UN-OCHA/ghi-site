<?php

namespace Drupal\ghi_templates;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Base object entities.
 *
 * @ingroup ghi_templates
 */
class PageTemplateListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [];
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\ghi_templates\PageTemplateInterface $entity */
    $row = [];
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.page_template.canonical',
      [$entity->getEntityTypeId() => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
