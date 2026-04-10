<?php

namespace Drupal\ghi_plans\ApiObjects\Partials;

use Drupal\hpc_api\ApiObjects\ApiObjectBase;

/**
 * Abstraction class for a project cluster partial object.
 *
 * This kind of partial object is a stripped-down, limited-data, object that
 * appears in some specific endpoints. We map this here to provide type hinting
 * and abstracted data access.
 */
class PlanProjectCluster extends ApiObjectBase {

  /**
   * The name.
   *
   * @var string
   */
  protected string $name;

  /**
   * The icon.
   *
   * @var string|null
   */
  protected ?string $icon;

  /**
   * Define the dimension items used in queries.
   */
  const GRAPHQL_ITEMS = [
    'Id',
    'Name',
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(object $data) {
    parent::__construct($data);
    $this->name = $data->Name;
    $this->icon = $data->Icon ?? NULL;
  }

  /**
   * Get the name of the cluster.
   *
   * @return string
   *   The name.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Get the icon for the cluster.
   *
   * @return string
   *   The icon string.
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

}
