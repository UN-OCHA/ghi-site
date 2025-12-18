<?php

namespace Drupal\ghi_plans\ApiObjects\Entities;

use Drupal\ghi_plans\Helpers\PlanEntityHelper;

/**
 * Abstraction class for API governing entity objects.
 */
class GoverningEntity extends EntityObjectBase {

  const ENTITY_REF_CODE = 'CL';

  const GRAPHQL_ITEMS = "
    Id
    Name
    Description
    PlanId
    EntityTypeId
    HpcEntityPrototypeId
    CustomReference
    ComposedReference
  ";

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $data = $this->getRawData();
    $_entity_version = $this->getEntityVersion($data);
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
      'plan_id' => $data->plan?->Id ?? NULL,
      'ref_code' => $prototype?->getRefCode() ?? NULL,
      'ref_codes_children' => array_map(function ($child) {
        return $child->refCode;
      }, $prototype?->getChildren() ?: []),
      'entity_type' => $prototype?->getType() ?? NULL,
      'entity_prototype_name' => $prototype?->getNameSingular(),
      'entity_prototype_id' => $data->HpcEntityPrototypeId,
      'order_number' => 0,
      'custom_reference' => $data->CustomReference,
      'composed_reference' => $data->ComposedReference ?? NULL,
      'sort_key' => ($prototype?->getOrderNumber() ?? '') . ($data->CustomReference ?? NULL),
      'icon' => $_entity_version?->value?->icon ?: $_entity_version?->value?->icon,

      // Legacy support.
      'custom_id' => $data->CustomReference,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityVersion() {
    return $this->getRawData()->governingEntityVersion ?? NULL;
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
      '@type' => $this->entity_prototype_name,
      '@name' => $this->name,
      '@custom_reference' => $this->custom_reference,
    ]);
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
    return array_merge([$this->ref_code], $this->ref_codes_children);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): ?string {
    return $this->getDisplayName();
  }

}
