<?php

namespace Drupal\hpc_api\ApiObjects;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableDependencyTrait;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for API objects.
 */
abstract class ApiObjectBase implements ApiObjectInterface, CacheableDependencyInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;
  use CacheableDependencyTrait;

  /**
   * The original data for an object from the HPC API.
   *
   * @var object
   */
  protected $data;

  /**
   * The mapped data for an object from the HPC API.
   *
   * @var object
   */
  protected $map;

  /**
   * {@inheritdoc}
   */
  public function __construct($data) {
    $this->setRawData($data);
    $this->updateMap();
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return (int) ($this->map?->id ?? ($this->data->id ?? $this->data->Id));
  }

  /**
   * {@inheritdoc}
   */
  public static function getGraphQlItems() {
    try {
      $items = (new \ReflectionClassConstant(get_called_class(), 'GRAPHQL_ITEMS'))->getValue();
      assert(is_array($items));
      return $items;
    }
    catch (\Exception $e) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRawData() {
    return $this->data ?: NULL;
  }

  /**
   * Set the raw data for the attachment, as returned by the API.
   *
   * @param object $data
   *   The raw data object.
   */
  protected function setRawData($data) {
    $this->data = $data;
  }

  /**
   * Update the internal map.
   */
  protected function updateMap() {
    $this->map = $this->map($this->data);
  }

  /**
   * Access mapped properties.
   *
   * @param string $property
   *   The property to retrieve.
   *
   * @return mixed|null
   *   The property value if it's available.
   */
  public function __get($property) {
    return $this->map->$property ?? NULL;
  }

  /**
   * Allow for empty or isset checks using magical accessors.
   *
   * @param string $property
   *   The property to check.
   *
   * @return bool
   *   TRUE if the value is present and not empty, FALSE otherwise.
   */
  public function __isset($property) {
    return property_exists($this->map, $property) && !empty($this->map->$property);
  }

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    $array = (array) $this->map ?? [];
    foreach ($array as $key => $item) {
      if (is_object($item) && method_exists($item, 'toArray')) {
        $array[$key] = $item->toArray();
      }
      if (is_array($item)) {
        foreach ($item as $_key => $_item) {
          if (is_object($_item) && method_exists($_item, 'toArray')) {
            $array[$key][$_key] = $_item->toArray();
          }
        }
      }
    }
    return $array;
  }

  /**
   * Map the raw data.
   *
   * @return object
   *   An object with the mapped data.
   */
  abstract protected function map();

  /**
   * Set the cache tags for this object.
   *
   * @param array $cache_tags
   *   The cache tags for this object.
   */
  public function setCacheTags($cache_tags) {
    $this->cacheTags = Cache::mergeTags($this->cacheTags, $cache_tags);
  }

  /**
   * Serialize the data for this object.
   *
   * @return array
   *   An array with serialized data for this object.
   */
  public function __serialize() {
    return ['data' => serialize($this->data)];
  }

  /**
   * Unserialize this object based on the given data.
   *
   * @param array $data
   *   The serialized data.
   */
  public function __unserialize(array $data) {
    if (empty($data['data'])) {
      return;
    }
    $this->setRawData(unserialize($data['data']));
    if ($this->data) {
      $this->updateMap();
    }
  }

}
