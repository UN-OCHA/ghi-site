<?php

namespace Drupal\hpc_api\ApiObjects;

use Drupal\Core\Cache\Cache;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for API objects.
 */
abstract class ApiObjectBase implements ApiObjectInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The original data for an object from the HPC API.
   *
   * @var object
   */
  private $data;

  /**
   * The mapped data for an object from the HPC API.
   *
   * @var object
   */
  private $map;

  /**
   * The cache tags.
   *
   * @var string[]
   */
  protected $cacheTags = [];

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
    return (int) $this->data->id;
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
   * Represent this as an array.
   *
   * @return array
   *   The mapped data as an array.
   */
  public function toArray() {
    return (array) $this->map ?? [];
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
    $this->cacheTags = Cache::mergeTags($cache_tags);
  }

  /**
   * Get the cache tags for this object.
   *
   * @return array
   *   The cache tags for this object.
   */
  public function getCacheTags() {
    return $this->cacheTags;
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
