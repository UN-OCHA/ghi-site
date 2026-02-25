<?php

namespace Drupal\ghi_plans\ApiObjects;

use Drupal\Core\Url;
use Drupal\ghi_base_objects\ApiObjects\BaseObject;
use Drupal\hpc_common\Helpers\CommonHelper;

/**
 * Abstraction class for API organization objects.
 */
class Organization extends BaseObject {

  /**
   * Define the dimension items used in queries.
   */
  const GRAPHQL_ITEMS = [
    'Id',
    'Name',
    'NativeName',
    'Abbreviation',
    'url',
  ];

  /**
   * A list of clusters.
   *
   * @var array
   */
  public $clusters;

  /**
   * Map the raw data.
   *
   * @return object
   *   An object with the mapped data.
   */
  protected function map() {
    $data = $this->getRawData();
    return (object) [
      'id' => $data->Id ?? ($data->id ?? NULL),
      'name' => $data->Name ?? ($data->name ?? NULL),
      'abbreviation' => $data->Abbreviation ?? ($data->abbreviation ?? NULL),
      'url' => CommonHelper::assureWellFormedUri($data->Url ?? ''),
    ];
  }

  /**
   * Get the abbreviation.
   *
   * @return string|null
   *   The abbreviation of the organization.
   */
  public function getAbbreviation(): ?string {
    return $this->map->abbreviation;
  }

  /**
   * Get the url.
   *
   * @return \Drupal\Core\Url
   *   The url of the organization.
   */
  public function getUrl(?array $options = []): ?Url {
    return $this->url ? Url::fromUri($this->url, $options) : NULL;
  }

  /**
   * Get the names of the associated clusters.
   *
   * @return string[]
   *   An array of cluster names.
   */
  public function getClusterNames() {
    return array_map(function ($cluster) {
      return $cluster->name;
    }, $this->map->clusters);
  }

}
