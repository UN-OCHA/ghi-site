<?php

namespace Drupal\ghi_plans\Entity;

use Drupal\ghi_base_objects\Entity\BaseObject;

/**
 * Bundle class for plan base objects.
 */
class GoverningEntity extends BaseObject {

  /**
   * Get the plan object that this governing entity belongs to.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface
   *   The plan base object.
   */
  public function getPlan() {
    return $this->get('field_plan')->entity ?? NULL;
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
    if ($icon = $entity->icon) {
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
  public function getSourceObject() {
    return $this->getEntityQuery()->getEntity('governingEntity', $this->getSourceId());
  }

  /**
   * Get the entity query.
   *
   * @return \Drupal\ghi_plans\Plugin\EndpointQuery\EntityQuery
   *   The entity query object.
   */
  private function getEntityQuery() {
    return self::getEndpointQueryManager()->createInstance('entity_query');
  }

  /**
   * Get the icon query.
   *
   * @return \Drupal\ghi_plans\Plugin\EndpointQuery\EntityQuery
   *   The icon query object.
   */
  private function getIconQuery() {
    return self::getEndpointQueryManager()->createInstance('icon_query');
  }

  /**
   * Get the endpoint query manager.
   *
   * @return \Drupal\hpc_api\Query\EndpointQueryManager
   *   The endpoint query manager.
   */
  private static function getEndpointQueryManager() {
    return \Drupal::service('plugin.manager.endpoint_query_manager');
  }

}
