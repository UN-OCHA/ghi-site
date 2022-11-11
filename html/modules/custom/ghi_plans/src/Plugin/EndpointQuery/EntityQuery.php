<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity;
use Drupal\ghi_plans\ApiObjects\Entities\PlanEntity;
use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for entities.
 *
 * @EndpointQuery(
 *   id = "entity_query",
 *   label = @Translation("Entity query"),
 *   endpoint = {
 *     "public" = "public/{entity_type}/{entity_id}",
 *     "authenticated" = "{entity_type}/{entity_id}",
 *     "version" = "v2"
 *   }
 * )
 */
class EntityQuery extends EndpointQueryBase implements ContainerFactoryPluginInterface {

  /**
   * Get an entity by type and id.
   *
   * @param string $entity_type
   *   The entity type to query.
   * @param int $entity_id
   *   The entity id to query.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface
   *   The entity object.
   */
  public function getEntity($entity_type, $entity_id) {
    $data = $this->getData([
      'entity_type' => $entity_type,
      'entity_id' => $entity_id,
    ]);
    if (empty($data)) {
      return NULL;
    }

    switch ($entity_type) {
      case 'governingEntity':
        return new GoverningEntity($data);

      case 'planEntity':
        return new PlanEntity($data);
    }

    return NULL;
  }

}
