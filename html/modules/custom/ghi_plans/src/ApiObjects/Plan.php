<?php

namespace Drupal\ghi_plans\ApiObjects;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;
use Drupal\ghi_plans\Entity\Plan as EntityPlan;
use Drupal\hpc_common\Helpers\StringHelper;

/**
 * Abstraction class for API plan objects.
 */
class Plan extends BaseObject implements PlanEntityInterface {

  /**
   * Map the raw data.
   *
   * This uses only what we needed up to now. More properties can be mapped if
   * needed.
   *
   * @return object
   *   An object with the mapped data.
   */
  protected function map() {
    $data = $this->getRawData();
    $categories = $data->categories;
    $plan_types = array_filter($categories, function ($category) {
      return strtolower($category->group) == 'plantype';
    });
    return (object) [
      'id' => $data->id,
      'name' => $data->planVersion->name,
      'plan_type' => count($plan_types) ? reset($plan_types)->name : NULL,
      'last_published_period' => $data->planVersion->lastPublishedReportingPeriodId,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getBundle() {
    return 'plan';
  }

  /**
   * Get the base object entity corresponding to this API object.
   *
   * @return \Drupal\ghi_plans\Entity\Plan
   *   The plan entity.
   */
  public function getEntity() {
    $entity = parent::getEntity();
    return $entity instanceof EntityPlan ? $entity : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->getName();
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomName($type) {
    return $this->plan_type ? StringHelper::getAbbreviation($this->plan_type) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeRefCode() {
    return 'PL';
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType() {
    return lcfirst((new \ReflectionClass($this))->getShortName());
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeName() {
    $pieces = preg_split('/(?=[A-Z])/', $this->getEntityType());
    return ucfirst(strtolower(implode(' ', $pieces)));
  }

}
