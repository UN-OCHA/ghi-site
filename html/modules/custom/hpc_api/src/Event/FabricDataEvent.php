<?php

namespace Drupal\hpc_api\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\hpc_api\Query\FabricQuery;

/**
 * Wraps a fabric data event for event listeners.
 */
class FabricDataEvent extends Event {

  /**
   * The fabric query.
   *
   * @var \Drupal\hpc_api\Query\FabricQuery
   */
  protected $query;

  /**
   * The endpoint data.
   *
   * @var mixed
   */
  protected $data;

  /**
   * Constructs a migration map event object.
   *
   * @param \Drupal\hpc_api\Query\FabricQuery $query
   *   The fabric query.
   * @param mixed $data
   *   The query data.
   */
  public function __construct(FabricQuery $query, $data) {
    $this->query = $query;
    $this->data = $data;
  }

  /**
   * Gets the fabric query.
   *
   * @return \Drupal\hpc_api\Query\FabricQuery
   *   The fabric query that caused the event to fire.
   */
  public function getQuery(): FabricQuery {
    return $this->query;
  }

  /**
   * Gets the query data.
   *
   * @return mixed
   *   The query data that caused the event to fire.
   */
  public function getData(): mixed {
    return $this->data;
  }

  /**
   * Gets the query data.
   *
   * @param mixed $data
   *   The query data that caused the event to fire.
   */
  public function setData($data): void {
    $this->data = $data;
  }

}
