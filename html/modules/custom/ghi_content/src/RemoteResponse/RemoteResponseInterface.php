<?php

namespace Drupal\ghi_content\RemoteResponse;

/**
 * Interface for RemoteResponse objects.
 */
interface RemoteResponseInterface {

  /**
   * Check if the response has the given property.
   *
   * @param string $key
   *   The property to check for.
   *
   * @return bool
   *   TRUE if the response has the given key, FALSE otherwhise.
   */
  public function has($key);

  /**
   * Get the given property from the response.
   *
   * @param string $key
   *   The property to check for.
   *
   * @return mixed
   *   The property value.
   */
  public function get($key);

  /**
   * Get the full response data.
   *
   * @return object
   *   The full response content object.
   */
  public function getData();

  /**
   * Set the response data object.
   *
   * @param object $data
   *   The data to set for the response.
   */
  public function setData(object $data);

  /**
   * Get the response code.
   *
   * @return int
   *   The response code.
   */
  public function getCode();

  /**
   * Set the response code.
   *
   * @param int $code
   *   The response code.
   */
  public function setCode($code);

  /**
   * Get the response status.
   *
   * @return bool
   *   TRUE if the request was successfull, FALSE otherwhise.
   */
  public function getStatus();

  /**
   * Whether the request resulted in a "forbidden" response.
   *
   * @return bool
   *   TRUE if the request was forbidden, FALSE otherwhise.
   */
  public function isForbidden();

}
