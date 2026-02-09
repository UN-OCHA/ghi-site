<?php

namespace Drupal\ghi_plans\Traits;

use Drupal\ghi_base_objects\Plugin\FabricQuery\CountryQuery;
use Drupal\ghi_base_objects\Plugin\FabricQuery\LocationQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentPrototypeQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\EntityPrototypeQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\EntityTypeQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\MeasurementQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\OrganizationQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\PlanEntityQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\PlanQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\ProjectQuery;
use Drupal\hpc_api\Traits\FabricQueryTrait;

/**
 * Trait to help with plan related fabric queries.
 */
trait PlanQueryTrait {

  use FabricQueryTrait;

  /**
   * Get the plan query.
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
   * Get the measurement query.
   *
   * @return \Drupal\ghi_plans\Plugin\FabricQuery\MeasurementQuery|null
   *   The measurement query or NULL.
   */
  protected static function getMeasurementQuery(): ?MeasurementQuery {
    $query = self::getQueryInstance('measurement');
    return $query instanceof MeasurementQuery ? $query : NULL;
  }

  /**
   * Get the location query.
   *
   * @return \Drupal\ghi_base_objects\Plugin\FabricQuery\LocationQuery|null
   *   The location query or NULL.
   */
  protected static function getLocationQuery(): ?LocationQuery {
    $query = self::getQueryInstance('organization');
    return $query instanceof LocationQuery ? $query : NULL;
  }

  /**
   * Get the organization query.
   *
   * @return \Drupal\ghi_plans\Plugin\FabricQuery\OrganizationQuery|null
   *   The organization query or NULL.
   */
  protected static function getOrganizationQuery(): ?OrganizationQuery {
    $query = self::getQueryInstance('organization');
    return $query instanceof OrganizationQuery ? $query : NULL;
  }

  /**
   * Get the project query.
   *
   * @return \Drupal\ghi_plans\Plugin\FabricQuery\ProjectQuery|null
   *   The project query or NULL.
   */
  protected static function getProjectQuery(): ?ProjectQuery {
    $query = self::getQueryInstance('project');
    return $query instanceof ProjectQuery ? $query : NULL;
  }

  /**
   * Get the country query.
   *
   * @return \Drupal\ghi_base_objects\Plugin\FabricQuery\CountryQuery|null
   *   The country query or NULL.
   */
  protected static function getCountryQuery(): ?CountryQuery {
    $query = self::getQueryInstance('country');
    return $query instanceof CountryQuery ? $query : NULL;
  }

}
