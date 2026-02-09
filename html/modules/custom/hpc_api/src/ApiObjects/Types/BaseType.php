<?php

namespace Drupal\hpc_api\ApiObjects\Types;

use Drupal\hpc_api\ApiObjects\ApiObjectBase;

/**
 * Base class for API type objects.
 */
abstract class BaseType extends ApiObjectBase {

  public const GRAPHQL_ITEMS = ['Id', 'Name', 'Description'];

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
   * Get the name of the type.
   *
   * @return string
   *   The name.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Get the description of the type.
   *
   * @return string|null
   *   The description.
   */
  public function getDescription(): ?string {
    return $this->description;
  }

}
