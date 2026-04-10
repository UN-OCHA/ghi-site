<?php

namespace Drupal\ghi_plans\ApiObjects\Entities;

use Drupal\ghi_plans\ApiObjects\Contact;
use Drupal\hpc_api\ApiObjects\Resource;

/**
 * Abstraction class for API governing entity objects.
 */
class GoverningEntity extends EntityObjectBase {

  /**
   * The icon name.
   *
   * @var string|null
   */
  protected ?string $icon;

  /**
   * The contacts.
   *
   * @var \Drupal\ghi_plans\ApiObjects\Contact[]
   */
  protected array $contacts;

  /**
   * The tags.
   *
   * @var string[]
   */
  protected array $tags;

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
  public function __construct(object $data) {
    parent::__construct($data);
    $contacts = array_filter($data->coordinationEntityContact?->items ?? [], fn ($item) => !empty($item->contact));

    $this->icon = NULL;
    $this->contacts = array_map(fn ($item): Contact => new Contact($item->contact), $contacts);
    $this->tags = !empty($data->HPCTags) ? explode('|', $data->HPCTags) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayName(): ?string {
    return $this->entityName;
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
   * Get the icon for this entity.
   *
   * @return string|null
   *   The name of the icon.
   */
  public function getIcon(): ?string {
    return $this->icon;
  }

  /**
   * Check if the entity has an icon.
   *
   * @return bool
   *   TRUE if the entity has an icon, FALSE otherwise..
   */
  public function hasIcon(): bool {
    return $this->getIcon() !== NULL;
  }

  /**
   * Get the contacts for this entity.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Contact[]
   *   An array of contact objects.
   */
  public function getContacts(): array {
    return $this->contacts;
  }

  /**
   * Get the tags.
   *
   * @return string[]
   *   An array of tag names.
   */
  public function getTags(): array {
    return $this->tags ?: [];
  }

}
