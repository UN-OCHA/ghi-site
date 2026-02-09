<?php

namespace Drupal\hpc_api\ApiObjects\Types;

/**
 * Class for metric type objects.
 */
class MetricType extends BaseType {

  const GRAPHQL_ITEMS = ['Id', 'Name', 'OtherName', 'HPCType'];

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $data = $this->getRawData();
    return (object) [
      'id' => $data->Id,
      'name' => $data->Name,
      'machine_name' => $data->HPCType ?? NULL,
      'label' => $data->OtherName ?? NULL,
    ];
  }

  /**
   * Get the label of the type.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string {
    return $this->map->label ?: $this->getName();
  }

  /**
   * Get the machine name for the metric.
   *
   * @return string
   *   The machine name for the metric.
   */
  public function getMachineName(): string {
    if ($this->map->machine_name) {
      return $this->map->machine_name;
    }
    $map = [
      'Population' => 'totalPopulation',
    ];
    return $map[$this->getName()] ?? lcfirst(str_replace(' ', '', $this->getName()));
  }

}
