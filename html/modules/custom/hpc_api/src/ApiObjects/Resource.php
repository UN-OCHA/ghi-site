<?php

namespace Drupal\hpc_api\ApiObjects;

use Drupal\Core\Url;

/**
 * Class for resource objects.
 */
class Resource extends ApiObjectBase {

  /**
   * Define the dimension items used in queries.
   */
  const GRAPHQL_ITEMS = [
    'Id',
    'Name',
    'MimeType',
    'URL',
    'Credit',
  ];

  /**
   * Map the raw data.
   *
   * This uses only what we needed up to now. More properties can be mapped if
   * needed.
   *
   * @return object
   *   An object with the mapped data.
   */
  protected function map() {
    $data = $this->getRawData();
    return (object) [
      'id' => $data->Id,
      'name' => $data->Name,
      'mimetype' => $data->MimeType,
      'url' => $data->URL,
      'credit' => $data->Credit,
    ];
  }

  /**
   * Get the name of the resource.
   *
   * @return string
   *   The name of the resource.
   */
  public function getName() {
    return $this->map->name;
  }

  /**
   * Get the mimetype of the resource.
   *
   * @return string
   *   The mimetype of the resource.
   */
  public function getMimeType(): string {
    return (string) $this->map->mimetype;
  }

  /**
   * Get the URL.
   *
   * @return \Drupal\Core\Url
   *   The URL of the resource.
   */
  public function getUrl(): Url {
    return Url::fromUri($this->map->url);
  }

  /**
   * Get the credits.
   *
   * @return string
   *   The credits as a string. Can be empty.
   */
  public function getCredit() {
    return (string) $this->map->credit;
  }

}
