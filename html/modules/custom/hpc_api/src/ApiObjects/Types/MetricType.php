<?php

namespace Drupal\hpc_api\ApiObjects\Types;

use Drupal\hpc_api\ApiObjects\Type;
use Drupal\hpc_common\Helpers\StringHelper;

/**
 * Class for metric type objects.
 */
class MetricType extends Type {

  const GRAPHQL_ITEMS = ['Id', 'Name', 'OtherName', 'NameFr', 'NameEs', 'HPCType', 'LabelLookup'];

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
      'locale' => (object) [
        'fr' => $data->NameFr ?? NULL,
        'es' => $data->NameEs ?? NULL,
      ],
      'lookup' => !empty($data->LabelLookup) ? explode('|', trim($data->LabelLookup, '|')) : [],
    ];
  }

  /**
   * Get the label of the type.
   *
   * @return string
   *   The label.
   */
  public function getLabel(?string $langcode = 'en'): string {
    if (in_array($langcode, ['fr', 'es']) && $label = $this->map->locale->$langcode ?? NULL) {
      return $label;
    }
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
      return StringHelper::camelCaseToUnderscoreCase($this->map->machine_name);
    }
    return StringHelper::camelCaseToUnderscoreCase(lcfirst(str_replace(' ', '', $this->getName())));
  }

  /**
   * Match a metric against the given string.
   *
   * @param string $string
   *   The string to match for.
   *
   * @return bool
   *   TRUE if string matches any of the labels, FALSE otherwise.
   */
  public function matches($string): bool {
    return strtolower($string) == strtolower($this->map->locale->fr ?? '')
        || strtolower($string) == strtolower($this->map->locale->es ?? '')
        || strtolower($string) == strtolower($this->map->name ?? '')
        || in_array(strtolower($string), $this->map->lookup);
  }

}
