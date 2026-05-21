<?php

namespace Drupal\ghi_plans\Entity;

use Drupal\ghi_base_objects\Entity\BaseObject;
use Drupal\ghi_base_objects\Entity\BaseObjectChildInterface;
use Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity as EntitiesGoverningEntity;
use Drupal\ghi_plans\Traits\PlanQueryTrait;

/**
 * Bundle class for governing entity base objects.
 */
class GoverningEntity extends BaseObject implements BaseObjectChildInterface {

  use PlanQueryTrait;

  const BUNDLE = 'governing_entity';

  /**
   * {@inheritdoc}
   */
  public function getParentBaseObject(): ?Plan {
    return $this->getPlan();
  }

  /**
   * {@inheritdoc}
   */
  public function labelWithParent(): string {
    return $this->getParentBaseObject()->label() . ': ' . $this->label();
  }

  /**
   * Get the plan object that this governing entity belongs to.
   *
   * @return \Drupal\ghi_plans\Entity\Plan|null
   *   The plan base object or NULL.
   */
  public function getPlan(): ?Plan {
    if (!$this->hasField('field_plan')) {
      return NULL;
    }
    $plan = $this->get('field_plan')->entity;
    return $plan instanceof Plan ? $plan : NULL;
  }

  /**
   * Get the icon name for this governing entity.
   *
   * @return string|null
   *   The icon name or NULL.
   */
  public function getIcon(): ?string {
    if (!$this->hasField('field_icon')) {
      return NULL;
    }
    return $this->get('field_icon')->value ?? NULL;
  }

  /**
   * Get the icon embed code for the entity.
   *
   * @return string|null
   *   The icon embed code or NULL.
   */
  public function getIconEmbedCode(): ?string {
    $icon = $this->getIcon();
    return $icon ? $this->getIconQuery()->getIconEmbedCode($icon) : NULL;
  }

  /**
   * Get the source object from the API.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity|null
   *   The entity object or NULL.
   */
  public function getSourceObject(): ?EntitiesGoverningEntity {
    $entity = $this->getEntityQuery()?->getEntity('governingEntity', $this->getSourceId());
    assert($entity === NULL || $entity instanceof EntitiesGoverningEntity);
    return $entity;
  }

}
