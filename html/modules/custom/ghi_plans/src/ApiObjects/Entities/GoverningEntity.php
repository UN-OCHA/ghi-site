<?php

namespace Drupal\ghi_plans\ApiObjects\Entities;

use Drupal\ghi_plans\Helpers\PlanEntityHelper;

/**
 * Abstraction class for API governing entity objects.
 */
class GoverningEntity extends EntityObjectBase {

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $entity = $this->getRawData();
    $entity_version = $this->getEntityVersion($entity);
    if (!property_exists($entity, 'entityPrototype') && !empty($entity->entityPrototypeId)) {
      $entity->entityPrototype = PlanEntityHelper::getEntityPrototype($entity->entityPrototypeId);
    }
    $prototype = $entity->entityPrototype;

    return (object) [
      'id' => $entity->id,
      'name' => $entity->composedReference . ': ' . $entity_version->name,
      'group_name' => $entity->composedReference . ': ' . $entity_version->name,
      'display_name' => $entity->composedReference . ': ' . $entity_version->name,
      'plural_name' => $prototype->value->name->en->plural,
      'description' => property_exists($entity_version->value, 'description') ? $entity_version->value->description : NULL,
      'entity_name' => $entity_version->name,
      'ref_code' => $prototype->refCode,
      'entity_type' => $prototype->type,
      'entity_prototype_name' => $prototype->value->name->en->singular,
      'entity_prototype_id' => $prototype->id,
      'order_number' => $entity_version->value->orderNumber ?? 0,
      'custom_reference' => $entity_version->customReference,
      'composed_reference' => $entity->composedReference,
      'sort_key' => property_exists($entity_version->value, 'orderNumber') ? $entity_version->value->orderNumber : ($prototype->orderNumber . ($entity->customReference ?? NULL)),
      'icon' => !empty($entity_version->value->icon) ? $entity_version->value->icon : NULL,
      'tags' => property_exists($entity_version, 'tags') ? $entity_version->tags : [],
      'parent_id' => $entity->parentId ?? NULL,

      // Legacy support.
      'custom_id' => $entity_version->customReference,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityVersion() {
    return $this->getRawData()->governingEntityVersion;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityName() {
    return $this->entity_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getFullName() {
    return $this->t('@type @name (@custom_reference)', [
      '@type' => $this->entity_prototype_name,
      '@name' => $this->name,
      '@custom_reference' => $this->custom_reference,
    ]);
  }

}
