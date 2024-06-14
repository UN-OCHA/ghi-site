<?php

namespace Drupal\ghi_plan_clusters\Entity;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\SubpageNode;

/**
 * Bundle class for plan cluster nodes.
 */
class PlanCluster extends SubpageNode implements PlanClusterInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    if ($override = $this->getTitleOverride()) {
      return $override;
    }
    return parent::label();
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    if ($override = $this->getTitleOverride()) {
      return $override;
    }
    return parent::getTitle();
  }

  /**
   * Get the title override.
   *
   * @return string|null
   *   The title override if set.
   */
  private function getTitleOverride() {
    if (!$this->hasField(self::TITLE_OVERRIDE_FIELD_NAME)) {
      return NULL;
    }
    return $this->get(self::TITLE_OVERRIDE_FIELD_NAME)->value ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitleOverride($title_override) {
    if (!$this->hasField(self::TITLE_OVERRIDE_FIELD_NAME)) {
      return NULL;
    }
    $this->get(self::TITLE_OVERRIDE_FIELD_NAME)->setValue($title_override);
  }

  /**
   * {@inheritdoc}
   */
  public function getParentNode() {
    $entity = $this->getPlanClusterManager()->loadSectionForClusterNode($this);
    return $entity instanceof SectionNodeInterface ? $entity : NULL;
  }

  /**
   * Get the logframe node associated to the plan cluster.
   *
   * @return \Drupal\ghi_subpages\Entity\LogframeSubpage|null
   *   A logframe node object.
   */
  public function getLogframeNode() {
    $nodes = $this->entityTypeManager()->getStorage($this->getEntityTypeId())->loadByProperties([
      'type' => 'logframe',
      'field_entity_reference' => $this->id(),
    ]);
    return count($nodes) ? reset($nodes) : NULL;
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
