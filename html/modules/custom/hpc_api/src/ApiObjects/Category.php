<?php

namespace Drupal\hpc_api\ApiObjects;

/**
 * Base class for API type objects.
 */
abstract class Category extends ApiObjectBase implements CategoryInterface {

  /**
   * Define the dimension items used in queries.
   */
  const GRAPHQL_ITEMS = ['Id', 'Name', 'Description'];

  /**
   * The namespace for a category, e.g. ageGroup, genders.
   *
   * @var string
   */
  private string $namespace;

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $data = $this->getRawData();
    return (object) [
      'id' => $data->Id,
      'name' => $data->Name,
      'description' => $data->Description ?? NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): ?string {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function setNamespace(string $namespace) {
    $this->namespace = $namespace;
  }

  /**
   * {@inheritdoc}
   */
  public function getNamespace() {
    return $this->namespace;
  }

  /**
   * {@inheritdoc}
   */
  public function getUuid(): string {
    return $this->getNamespace() . ':' . $this->id();
  }

}
