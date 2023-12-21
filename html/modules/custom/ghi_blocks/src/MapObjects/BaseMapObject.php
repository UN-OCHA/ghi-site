<?php

namespace Drupal\ghi_blocks\MapObjects;

/**
 * Base class for map objects.
 */
abstract class BaseMapObject implements BaseMapObjectInterface {

  /**
   * The id of the map object.
   *
   * @var int
   */
  protected $id;

  /**
   * The name of the map object.
   *
   * @var string
   */
  protected $name;

  /**
   * The location ids of the map object.
   *
   * @var int[]
   */
  protected $locationIds;

  /**
   * Additional data to be stored for the object.
   *
   * @var array
   */
  protected $data;

  /**
   * {@inheritdoc}
   */
  public function __construct($id, $name, array $location_ids, array $data = []) {
    $this->id = $id;
    $this->name = $name;
    $this->locationIds = $location_ids;
    $this->data = $data;
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocationIds() {
    return $this->locationIds;
  }

}
