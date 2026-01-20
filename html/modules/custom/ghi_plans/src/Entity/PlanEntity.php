<?php

namespace Drupal\ghi_plans\Entity;

use Drupal\ghi_base_objects\Entity\BaseObject;
use Drupal\ghi_base_objects\Entity\BaseObjectChildInterface;

/**
 * Bundle class for governing entity base objects.
 */
class PlanEntity extends BaseObject implements BaseObjectChildInterface {

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

}
