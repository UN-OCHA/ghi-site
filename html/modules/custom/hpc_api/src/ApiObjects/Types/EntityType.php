<?php

namespace Drupal\hpc_api\ApiObjects\Types;

/**
 * Class for entity type objects.
 */
class EntityType extends BaseType {

  const GRAPHQL_ITEMS = ['Id', 'Name', 'Alias'];

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $data = $this->getRawData();
    return (object) [
      'id' => $data->Id,
      'name' => $data->Name,
      'label' => $data->Alias ?? NULL,
    ];
  }

  /**
   * Get the label of the type.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string {
    return $this->label ?: $this->getName();
  }

}
