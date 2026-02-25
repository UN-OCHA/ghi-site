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
   * Get the sector id that this governing entity belongs to.
   *
   * @return int|null
   *   The sector id or NULL.
   */
  public function getSectorId(): ?int {
    if (!$this->hasField('field_sector_id')) {
      return NULL;
    }
    $sector_id = $this->get('field_sector_id')->value;
    return $sector_id;
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
  public function getIconEmbedCode(): ?string {
    // @todo Update once the icons are in the data store.
    return NULL;
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
