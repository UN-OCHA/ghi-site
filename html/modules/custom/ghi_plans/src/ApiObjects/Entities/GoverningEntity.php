<?php

namespace Drupal\ghi_plans\ApiObjects\Entities;

use Drupal\ghi_plans\ApiObjects\Contact;
use Drupal\hpc_api\ApiObjects\Resource;

/**
 * Abstraction class for API governing entity objects.
 */
class GoverningEntity extends EntityObjectBase {

  const ENTITY_REF_CODE = 'CL';

  /**
   * Define the dimension items used in queries.
   */
  const GRAPHQL_ITEMS = [
    'Id',
    'Name',
    'Description',
    'PlanId',
    'EntityTypeId',
    'HpcEntityPrototypeId',
    'CustomReference',
    'ComposedReference',
    'HPCTags',
    // phpcs:disable Squiz.Arrays.ArrayDeclaration.KeySpecified
    "coordinationEntityContact" => ['items' => ['contact' => Contact::GRAPHQL_ITEMS]],
    'fieldClusterResource' => ['items' => ['resource' => Resource::GRAPHQL_ITEMS]],
    // phpcs:enable Squiz.Arrays.ArrayDeclaration.KeySpecified
  ];

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $data = $this->getRawData();
    $contacts = array_filter($data->coordinationEntityContact?->items ?? [], fn ($item) => !empty($item->contact));
    return (object) [
      'id' => $data->Id,
      'name' => ($data->ComposedReference ?? '') . ': ' . $data->Name,
      'group_name' => ($data->ComposedReference ?? '') . ': ' . $data->Name,
      'description' => $data->Description ?: NULL,
      'entity_name' => $data->Name,
      'plan_id' => $data->PlanId ?? NULL,
      'custom_reference' => $data->CustomReference,
      'composed_reference' => $data->ComposedReference ?? NULL,
      'icon' => NULL,
      'contacts' => array_map(fn ($item): Contact => new Contact($item->contact), $contacts),
      'tags' => !empty($data->HPCTags) ? explode('|', $data->HPCTags) : [],

      // Legacy support.
      'custom_id' => $data->CustomReference,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayName(): ?string {
    return $this->map->entity_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getFullName(): string {
    return (string) $this->t('@type @name (@custom_reference)', [
      '@type' => $this->getPrototypeName(),
      '@name' => $this->getName(),
      '@custom_reference' => $this->getCustomReference(),
    ]);
  }

  /**
   * Get the ref codes of all supported children.
   *
   * @return string[]
   *   An array of ref code strings.
   */
  protected function getChildrenRefCodes() {
    return array_map(function ($child) {
      return $child->refCode;
    }, $this->getPrototype()?->getChildren() ?: []);
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
    return array_merge([$this->getEntityTypeRefCode()], $this->getChildrenRefCodes());
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): ?string {
    return $this->getDisplayName();
  }

  /**
   * Get the contacts for this entity.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Contact[]
   *   An array of contact objects.
   */
  public function getContacts(): array {
    return $this->map->contacts;
  }

  /**
   * Get the tags.
   *
   * @return string[]
   *   An array of tag names.
   */
  public function getTags(): array {
    return $this->map->tags ?: [];
  }

}
