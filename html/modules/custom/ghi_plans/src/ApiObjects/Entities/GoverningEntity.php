<?php

namespace Drupal\ghi_plans\ApiObjects\Entities;

use Drupal\ghi_plans\Helpers\PlanEntityHelper;

/**
 * Abstraction class for API governing entity objects.
 */
class GoverningEntity extends EntityObjectBase {

  const ENTITY_REF_CODE = 'CL';

  /**
   * Define the dimension items used in queries.
   */
  const GRAPHQL_DIMENSION_ITEMS = [
    'Id',
    'Name',
    'Description',
    'PlanId',
    'EntityTypeId',
    'HpcEntityPrototypeId',
    'CustomReference',
    'ComposedReference',
  ];

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $data = $this->getRawData();
    $prototype = !empty($data->HpcEntityPrototypeId ?? NULL) ? PlanEntityHelper::getEntityPrototype($data->HpcEntityPrototypeId) : NULL;

    return (object) [
      'id' => $data->Id,
      'name' => ($data->ComposedReference ?? '') . ': ' . $data->Name,
      'group_name' => ($data->ComposedReference ?? '') . ': ' . $data->Name,
      'display_name' => ($data->ComposedReference ?? '') . ': ' . $data->Name,
      'singular_name' => $prototype?->getNameSingular(),
      'plural_name' => $prototype?->getNamePlural(),
      'description' => $data->Description ?: NULL,
      'entity_name' => $data->Name,
      'plan_id' => $data->PlanId ?? NULL,
      'ref_code' => $prototype?->getRefCode() ?? NULL,
      'ref_codes_children' => array_map(function ($child) {
        return $child->refCode;
      }, $prototype?->getChildren() ?: []),
      'entity_type' => $prototype?->getType() ?? NULL,
      'entity_prototype_name' => $prototype?->getNameSingular(),
      'entity_prototype_id' => $prototype?->id(),
      'order_number' => 0,
      'custom_reference' => $data->CustomReference,
      'composed_reference' => $data->ComposedReference ?? NULL,
      'sort_key' => ($prototype?->getOrderNumber() ?? '') . ($data->CustomReference ?? NULL),
      'icon' => NULL,

      // Legacy support.
      'custom_id' => $data->CustomReference,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayName() {
    return $this->entity_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getFullName() {
    return $this->t('@type @name (@custom_reference)', [
      '@type' => $this->getPrototypeName(),
      '@name' => $this->getName(),
      '@custom_reference' => $this->getCustomReference(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getSingularName(): string {
    return $this->map->singular_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluralName(): string {
    return $this->map->plural_name;
  }

  /**
   * Get valid ref codes for context management.
   *
   * @param bool $include_children
   *   Whether to inlude possible children. Default is TRUE.
   *
   * @return string[]
   *   An array of ref codes.
   */
  public function getValidRefCodes($include_children = TRUE) {
    return array_merge([$this->getEntityTypeRefCode()], $this->ref_codes_children);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): ?string {
    return $this->getDisplayName();
  }

  /**
   * Get the prototype name.
   *
   * @return string|null
   *   The prototype name.
   */
  public function getPrototypeName(): ?string {
    return $this->entity_prototype_name;
  }

}
