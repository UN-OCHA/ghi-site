<?php

namespace Drupal\ghi_plans\Helpers;

use Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity;
use Drupal\ghi_plans\ApiObjects\Entities\PlanEntity;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_common\Helpers\ArrayHelper;

/**
 * Helper class for handling plan structure logic.
 *
 * @phpcs:disable DrupalPractice.FunctionCalls.InsecureUnserialize
 */
class PlanEntityHelper {

  /**
   * Instantiate a plan entity object.
   *
   * @param object $entity
   *   The raw entity object from the API.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface|null
   *   An intantiated API entity object.
   */
  public static function getObject($entity) {
    if (property_exists($entity, 'planEntityVersion')) {
      return new PlanEntity($entity);
    }
    if (property_exists($entity, 'governingEntityVersion')) {
      return new GoverningEntity($entity);
    }
    return NULL;
  }

  /**
   * Get the governing entity objects from the plan data.
   *
   * @param object $plan_data
   *   The raw plan data from the API.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[]
   *   An array of API entity objects.
   */
  public static function getGoverningEntityObjects($plan_data) {
    if (empty($plan_data->governingEntities)) {
      return [];
    }
    $entites = $plan_data->governingEntities;
    $objects = [];
    foreach ($entites as $entity) {
      $objects[$entity->id] = self::getObject($entity);
    }
    ArrayHelper::sortObjectsByStringProperty($objects, 'sort_key', EndpointQuery::SORT_ASC);
    return $objects;
  }

  /**
   * Get the plan entity objects from the plan data.
   *
   * @param object $plan_data
   *   The raw plan data from the API.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[]
   *   An array of API entity objects.
   */
  public static function getPlanEntityObjects($plan_data) {
    if (empty($plan_data->planEntities)) {
      return [];
    }
    $entites = $plan_data->planEntities;
    $objects = [];
    foreach ($entites as $entity) {
      $objects[$entity->id] = self::getObject($entity);
    }
    ArrayHelper::sortObjectsByStringProperty($objects, 'sort_key', EndpointQuery::SORT_ASC);
    return $objects;
  }

  /**
   * Get plan entity data from the API.
   *
   * @param int $entity_id
   *   The plan entity id for which to retrieve the data.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface
   *   The plan entity object.
   */
  public static function getPlanEntity($entity_id) {
    /** @var \Drupal\hpc_api\Query\EndpointQuery $query */
    $query = \Drupal::service('hpc_api.endpoint_query');
    $query->setArguments([
      'endpoint' => 'planEntity/' . $entity_id,
      'api_version' => 'v2',
      'auth_method' => EndpointQuery::AUTH_METHOD_API_KEY,
      'query_args' => [
        'addPercentageOfTotalTarget' => 'true',
        'disaggregation' => 'false',
        'version' => 'current',
      ],
    ]);
    return new PlanEntity($query->getData());
  }

  /**
   * Get governing entity data from the API.
   *
   * @param int $entity_id
   *   The plan entity id for which to retrieve the data.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface
   *   The governing entity object.
   */
  public static function getGoverningEntity($entity_id) {
    /** @var \Drupal\hpc_api\Query\EndpointQuery $query */
    $query = \Drupal::service('hpc_api.endpoint_query');
    $query->setArguments([
      'endpoint' => 'governingEntity/' . $entity_id,
      'api_version' => 'v2',
      'auth_method' => EndpointQuery::AUTH_METHOD_API_KEY,
      'query_args' => [
        'addPercentageOfTotalTarget' => 'true',
        'disaggregation' => 'false',
        'version' => 'current',
      ],
    ]);
    return new GoverningEntity($query->getData());
  }

  /**
   * Get entity prototype data from the API.
   *
   * @param int $id
   *   The prototype id to retrieve.
   *
   * @return object
   *   The prototype object.
   */
  public static function getEntityPrototype($id) {
    /** @var \Drupal\hpc_api\Query\EndpointQuery $query */
    $query = \Drupal::service('hpc_api.endpoint_query');
    $query->setArguments([
      'endpoint' => 'plan/entity-prototype/' . $id,
      'api_version' => 'v2',
      'auth_method' => EndpointQuery::AUTH_METHOD_API_KEY,
    ]);
    return $query->getData();
  }

}
