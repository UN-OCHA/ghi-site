<?php

namespace Drupal\hpc_api\ApiObjects;

/**
 * Base class for API type objects.
 */
abstract class Type extends ApiObjectBase implements TypeInterface {

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
   * Define the dimension items used in queries.
   */
  const GRAPHQL_ITEMS = ['Id', 'Name', 'Description'];

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

}
