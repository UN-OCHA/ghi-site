<?php

namespace Drupal\hpc_api\ApiObjects;

/**
 * Base class for API type objects.
 */
abstract class Type extends ApiObjectBase implements TypeInterface {

  /**
   * Define the dimension items used in queries.
   */
  const GRAPHQL_ITEMS = ['Id', 'Name', 'Description'];

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

}
