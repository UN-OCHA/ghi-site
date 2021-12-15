<?php

namespace Drupal\ghi_content\RemoteResponse;

/**
 * Class that represents a response from a remote content source.
 */
class RemoteResponse implements RemoteResponseInterface {

  /**
   * The response code.
   *
   * @var int
   */
  private $code;

  /**
   * The response data.
   *
   * @var mixed
   */
  private $data;

  /**
   * Construct a RemoteResponse object.
   *
   * @param mixed $data
   *   The response data.
   * @param int $code
   *   The response code.
   */
  public function __construct($data = NULL, $code = NULL) {
    $this->data = $data;
    $this->code = $code;
  }

  /**
   * {@inheritdoc}
   */
  public function has($key) {
    return is_object($this->data) && property_exists($this->data, $key) && $this->data->$key !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function get($key) {
    return is_object($this->data) && property_exists($this->data, $key) ? $this->data->$key : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getData() {
    return $this->data;
  }

  /**
   * {@inheritdoc}
   */
  public function setData($data) {
    $this->data = $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getCode() {
    return $this->code;
  }

  /**
   * {@inheritdoc}
   */
  public function setCode($code) {
    $this->code = $code;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return $this->code == 200;
  }

  /**
   * {@inheritdoc}
   */
  public function isForbidden() {
    return $this->code == 403;
  }

}
