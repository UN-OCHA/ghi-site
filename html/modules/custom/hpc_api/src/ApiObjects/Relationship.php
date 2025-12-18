<?php

namespace Drupal\hpc_api\ApiObjects;

/**
 * Class for relationship objects.
 */
class Relationship extends ApiObjectBase {

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
      'type' => $data->RelationshipType,
      'source' => (object) [
        'type' => $data->FromEntityTypeId,
        'id' => $data->FromId,
      ],
      'target' => (object) [
        'type' => $data->ToEntityTypeId,
        'id' => $data->ToId,
      ],
    ];
  }

  /**
   * Get the identifier for the source.
   *
   * @return string
   *   The identifier for the source.
   */
  public function getType() {
    return $this->type;
  }

  /**
   * Get the identifier for the source.
   *
   * @return string
   *   The identifier for the source.
   */
  public function getSource() {
    return $this->source->type . ':' . $this->source->id;
  }

  /**
   * Get the source type id.
   *
   * @return int
   *   The type id for the source.
   */
  public function getSourceTypeId() {
    return $this->source->type;
  }

  /**
   * Get the source id.
   *
   * @return int
   *   The id for the source.
   */
  public function getSourceId() {
    return $this->source->id;
  }

  /**
   * Get the identifier for the target.
   *
   * @return string
   *   The identifier for the target.
   */
  public function getTarget() {
    return $this->target->type . ':' . $this->target->id;
  }

  /**
   * Get the target type id.
   *
   * @return int
   *   The type id for the target.
   */
  public function getTargetTypeId() {
    return $this->target->type;
  }

  /**
   * Get the target id.
   *
   * @return int
   *   The id for the target.
   */
  public function getTargetId() {
    return $this->target->id;
  }

}
