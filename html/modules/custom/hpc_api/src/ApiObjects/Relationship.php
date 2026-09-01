<?php

namespace Drupal\hpc_api\ApiObjects;

/**
 * Class for relationship objects.
 */
class Relationship extends ApiObjectBase {

  /**
   * The type.
   *
   * @var mixed
   */
  protected mixed $type;

  /**
   * The source.
   *
   * @var object
   */
  protected object $source;

  /**
   * The target.
   *
   * @var object
   */
  protected object $target;

  /**
   * Define the dimension items used in queries.
   */
  const GRAPHQL_ITEMS = [
    'Id',
    'FromEntityTypeId',
    'FromId',
    'ToEntityTypeId',
    'ToId',
    'RelationshipType',
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(object $data) {
    parent::__construct($data);
    $this->type = $data->RelationshipType;
    $this->source = (object) [
      'type' => $data->FromEntityTypeId,
      'id' => $data->FromId,
    ];
    $this->target = (object) [
      'type' => $data->ToEntityTypeId,
      'id' => $data->ToId,
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
