<?php

namespace Drupal\ghi_plans\ApiObjects;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;
use Drupal\ghi_plans\Entity\Plan as EntityPlan;

/**
 * Abstraction class for API plan objects.
 */
class Plan extends BaseObject {

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
    return (object) [
      'id' => $data->id,
      'name' => $data->planVersion->name,
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

}
