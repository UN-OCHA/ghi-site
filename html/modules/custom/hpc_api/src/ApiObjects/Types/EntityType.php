<?php

namespace Drupal\hpc_api\ApiObjects\Types;

use Drupal\hpc_api\ApiObjects\Type;

/**
 * Class for entity type objects.
 */
class EntityType extends Type {

  /**
   * The name.
   *
   * @var string
   */
  protected string $name;

  /**
   * The label.
   *
   * @var string|null
   */
  protected ?string $label;

  const GRAPHQL_ITEMS = ['Id', 'Name', 'Alias'];

  /**
   * {@inheritdoc}
   */
  public function __construct(object $data) {
    parent::__construct($data);
    $this->name = $data->Name;
    $this->label = $data->Alias ?? NULL;
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
