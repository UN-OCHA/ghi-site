<?php

namespace Drupal\ghi_plan_clusters\Entity;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_base_objects\Entity\BaseObjectAwareEntityInterface;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\SubpageNode;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;

/**
 * Bundle class for plan cluster nodes.
 */
class PlanCluster extends SubpageNode implements SubpageNodeInterface, BaseObjectAwareEntityInterface {

  use StringTranslationTrait;

  const BASE_OBJECT_FIELD_NAME = 'field_base_object';
  const SECTION_REFERENCE_FIELD_NAME = 'field_entity_reference';

  /**
   * {@inheritdoc}
   */
  public function getParentNode() {
    $entity = $this->getPlanClusterManager()->loadSectionForClusterNode($this);
    return $entity instanceof SectionNodeInterface ? $entity : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseObject() {
    if (!$this->hasField(self::BASE_OBJECT_FIELD_NAME)) {
      return NULL;
    }
    $base_object = $this->get(self::BASE_OBJECT_FIELD_NAME)->entity ?? NULL;
    return $base_object instanceof GoverningEntity ? $base_object : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function getBaseObjectType() {
    return \Drupal::entityTypeManager()->getStorage('base_object_type')->load('governing_entity');
  }

  /**
   * Get the plan cluster manager.
   *
   * @return \Drupal\ghi_plan_clusters\PlanClusterManager
   *   The plan cluster manager service.
   */
  private static function getPlanClusterManager() {
    return \Drupal::service('ghi_plan_clusters.manager');
  }

}
