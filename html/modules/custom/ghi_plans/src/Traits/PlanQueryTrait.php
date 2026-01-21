<?php

namespace Drupal\ghi_plans\Traits;

use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentPrototypeQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\EntityPrototypeQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\EntityTypeQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\OrganizationQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\PlanEntityQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\PlanQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Trait to help with plan related fabric queries.
 */
trait PlanQueryTrait {

  /**
   * Get the entity type query.
   *
   * @return \Drupal\ghi_plans\Plugin\FabricQuery\PlanQuery|null
   *   The plan query or NULL.
   */
  protected static function getPlanQuery(): ?PlanQuery {
    $query = self::getQueryInstance('plan');
    return $query instanceof PlanQuery ? $query : NULL;
  }

  /**
   * Get the plan entity query.
   *
   * @return \Drupal\ghi_plans\Plugin\FabricQuery\PlanEntityQuery
   *   The plan entity query or NULL.
   */
  protected static function getPlanEntityQuery(): ?PlanEntityQuery {
    $query = self::getQueryInstance('plan_entity');
    return $query instanceof PlanEntityQuery ? $query : NULL;
  }

  /**
   * Get the entity type query.
   *
   * @return \Drupal\ghi_plans\Plugin\FabricQuery\EntityTypeQuery|null
   *   The entity type query or NULL.
   */
  protected static function getEntityTypeQuery(): ?EntityTypeQuery {
    $query = self::getQueryInstance('entity_type');
    return $query instanceof EntityTypeQuery ? $query : NULL;
  }

  /**
   * Get the entity prototype query.
   *
   * @return \Drupal\ghi_plans\Plugin\FabricQuery\EntityPrototypeQuery|null
   *   The entity prototype query or NULL.
   */
  protected static function getEntityPrototypeQuery(): ?EntityPrototypeQuery {
    $query = self::getQueryInstance('entity_prototype');
    return $query instanceof EntityPrototypeQuery ? $query : NULL;
  }

  /**
   * Get the attachment query.
   *
   * @return \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery|null
   *   The attachment query or NULL.
   */
  protected static function getAttachmentQuery(): ?AttachmentQuery {
    $query = self::getQueryInstance('attachment');
    return $query instanceof AttachmentQuery ? $query : NULL;
  }

  /**
   * Get the attachment prototype query.
   *
   * @return \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentPrototypeQuery|null
   *   The attachment prototype query or NULL.
   */
  protected static function getAttachmentPrototypeQuery(): ?AttachmentPrototypeQuery {
    $query = self::getQueryInstance('attachment_prototype');
    return $query instanceof AttachmentPrototypeQuery ? $query : NULL;
  }

  /**
   * Get the organization query.
   *
   * @return \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentPrototypeQuery|null
   *   The organization query or NULL.
   */
  protected static function getOrganizationQuery(): ?OrganizationQuery {
    $query = self::getQueryInstance('organization');
    return $query instanceof OrganizationQuery ? $query : NULL;
  }

  /**
   * Get a query instance by id.
   *
   * @param string $plugin_id
   *   The plugin id of the fabric query plugin.
   *
   * @return \Drupal\hpc_api\Query\FabricQueryBase|null
   *   The query instance or NULL.
   */
  protected static function getQueryInstance($plugin_id): ?FabricQueryBase {
    $query_manager = self::getFabricQueryManager();
    $query = $query_manager->hasDefinition($plugin_id) ? $query_manager->createInstance($plugin_id) : NULL;
    return $query instanceof FabricQueryBase ? $query : NULL;
  }

  /**
   * Get the fabric query manager.
   *
   * @return \Drupal\hpc_api\Query\FabricQueryManager
   *   The fabric query manager service.
   */
  protected static function getFabricQueryManager() {
    return \Drupal::service('plugin.manager.fabric_query_manager');
  }

}
