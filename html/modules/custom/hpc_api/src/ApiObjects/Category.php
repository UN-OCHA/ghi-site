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
   * The name.
   *
   * @var string
   */
  protected string $name;

  /**
   * The description.
   *
   * @var string|null
   */
  protected ?string $description;

  /**
   * The namespace for a category, e.g. ageGroup, genders.
   *
   * @var string
   */
  private string $namespace;

  /**
   * {@inheritdoc}
   */
  public function __construct(object $data) {
    parent::__construct($data);
    $this->name = $data->Name;
    $this->description = $data->Description ?? NULL;
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
