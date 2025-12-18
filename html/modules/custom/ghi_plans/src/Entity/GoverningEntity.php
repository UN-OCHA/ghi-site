<?php

namespace Drupal\ghi_plans\Entity;

use Drupal\ghi_base_objects\Entity\BaseObject;
use Drupal\ghi_base_objects\Entity\BaseObjectChildInterface;
use Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity as EntitiesGoverningEntity;
use Drupal\ghi_plans\Plugin\FabricQuery\PlanEntityQuery;
use Drupal\hpc_api\Plugin\EndpointQuery\IconQuery;
use Drupal\hpc_api\Query\EndpointQueryManager;
use Drupal\hpc_api\Query\FabricQueryManager;

/**
 * Bundle class for governing entity base objects.
 */
class GoverningEntity extends BaseObject implements BaseObjectChildInterface {

  /**
   * {@inheritdoc}
   */
  public function getParentBaseObject() {
    return $this->getPlan();
  }

  /**
   * {@inheritdoc}
   */
  public function labelWithParent() {
    return $this->getParentBaseObject()->label() . ': ' . $this->label();
  }

  /**
   * Get the plan object that this governing entity belongs to.
   *
   * @return \Drupal\ghi_plans\Entity\Plan
   *   The plan base object.
   */
  public function getPlan() {
    if (!$this->hasField('field_plan')) {
      return NULL;
    }
    $plan = $this->get('field_plan')->entity;
    return $plan instanceof Plan ? $plan : NULL;
  }

  /**
   * Get the icon embed code for the entity.
   *
   * @return string|null
   *   The icon embed code or NULL.
   *
   * @todo We might want to import the icon as part of the Drupal data model
   * too at some point to prevent unnecessary turn-arounds.
   */
  public function getIconEmbedCode() {
    $entity = $this->getSourceObject();
    if ($entity && $icon = $entity->icon) {
      return $this->getIconQuery()->getIconEmbedCode($icon);
    }
    return NULL;
  }

  /**
   * Get the source object from the API.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity
   *   The entity object.
   */
  public function getSourceObject(): EntitiesGoverningEntity {
    $entity = $this->getEntityQuery()->getEntity('governingEntity', $this->getSourceId());
    assert($entity instanceof EntitiesGoverningEntity);
    return $entity;
  }

  /**
   * Get the entity query.
   *
   * @return \Drupal\ghi_plans\Plugin\FabricQuery\EntityQuery
   *   The entity query object.
   */
  private function getEntityQuery(): PlanEntityQuery {
    return self::getFabricQueryManager()->createInstance('plan_entity');
  }

  /**
   * Get the icon query.
   *
   * @return \Drupal\hpc_api\Plugin\EndpointQuery\IconQuery
   *   The icon query object.
   */
  private function getIconQuery(): IconQuery {
    return self::getEndpointQueryManager()->createInstance('icon_query');
  }

  /**
   * Get the endpoint query manager.
   *
   * @return \Drupal\hpc_api\Query\EndpointQueryManager
   *   The endpoint query manager.
   */
  private static function getEndpointQueryManager(): EndpointQueryManager {
    return \Drupal::service('plugin.manager.endpoint_query_manager');
  }

  /**
   * Get the fabric query manager.
   *
   * @return \Drupal\hpc_api\Query\FabricQueryManager
   *   The fabric query manager.
   */
  private static function getFabricQueryManager(): FabricQueryManager {
    return \Drupal::service('plugin.manager.fabric_query_manager');
  }

}
