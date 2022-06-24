<?php

namespace Drupal\hpc_api\ApiObjects;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for API objects.
 */
abstract class ApiObjectBase implements ApiObjectInterface {

  use StringTranslationTrait;

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
    return $this->data->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getRawData() {
    return $this->data;
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
    return (array) $this->map;
  }

  /**
   * Map the raw data.
   *
   * @return object
   *   An object with the mapped data.
   */
  abstract protected function map();

}
