<?php

namespace Drupal\hpc_api\ApiObjects\Types;

use Drupal\hpc_api\ApiObjects\Type;
use Drupal\hpc_api\Helpers\StringHelper;

/**
 * Class for metric type objects.
 */
class MetricType extends Type {

  /**
   * The name.
   *
   * @var string
   */
  protected string $name;

  /**
   * The machine name.
   *
   * @var string|null
   */
  protected ?string $machineName;

  /**
   * The label.
   *
   * @var string|null
   */
  protected ?string $label;

  /**
   * The locale.
   *
   * @var object
   */
  protected object $locale;

  /**
   * The lookup.
   *
   * @var array
   */
  protected array $lookup;

  const GRAPHQL_ITEMS = ['Id', 'Name', 'OtherName', 'NameFr', 'NameEs', 'HPCType', 'LabelLookup'];

  /**
   * {@inheritdoc}
   */
  public function __construct(object $data) {
    parent::__construct($data);
    $this->name = $data->Name;
    $this->machineName = $data->HPCType ?? NULL;
    $this->label = $data->OtherName ?? NULL;
    $this->locale = (object) [
      'fr' => $data->NameFr ?? NULL,
      'es' => $data->NameEs ?? NULL,
    ];
    $this->lookup = !empty($data->LabelLookup) ? explode('|', trim($data->LabelLookup, '|')) : [];
  }

  /**
   * Get the label of the type.
   *
   * @return string
   *   The label.
   */
  public function getLabel(?string $langcode = 'en'): string {
    if (in_array($langcode, ['fr', 'es']) && $label = $this->locale->$langcode ?? NULL) {
      return $label;
    }
    return $this->label ?: $this->getName();
  }

  /**
   * Get the machine name for the metric.
   *
   * @return string
   *   The machine name for the metric.
   */
  public function getMachineName(): string {
    if ($this->machineName) {
      return StringHelper::camelCaseToUnderscoreCase($this->machineName);
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
    return strtolower($string) == strtolower($this->locale->fr ?? '')
        || strtolower($string) == strtolower($this->locale->es ?? '')
        || strtolower($string) == strtolower($this->name ?? '')
        || in_array(strtolower($string), $this->lookup);
  }

}
