<?php

namespace Drupal\hpc_api\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\hpc_api\Query\EndpointQuery;

/**
 * Wraps an endpoint data event for event listeners.
 */
class EndpointDataEvent extends Event {

  /**
   * The endpoint query.
   *
   * @var \Drupal\hpc_api\Query\EndpointQuery
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
   * @param \Drupal\hpc_api\Query\EndpointQuery $query
   *   The endpoint query.
   * @param mixed $data
   *   The endpoint data.
   */
  public function __construct(EndpointQuery $query, $data) {
    $this->query = $query;
    $this->data = $data;
  }

  /**
   * Gets the endpoint query.
   *
   * @return \Drupal\hpc_api\Query\EndpointQuery
   *   The endpoint query that caused the event to fire.
   */
  public function getQuery(): EndpointQuery {
    return $this->query;
  }

  /**
   * Gets the endpoint data.
   *
   * @return mixed
   *   The endpoint data that caused the event to fire.
   */
  public function getData(): mixed {
    return $this->data;
  }

  /**
   * Gets the endpoint data.
   *
   * @param mixed $data
   *   The endpoint data that caused the event to fire.
   */
  public function setData($data): void {
    $this->data = $data;
  }

}
