<?php

namespace Drupal\hpc_api\Query;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Interface for endpoint query plugins.
 */
interface EndpointQueryPluginInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Wrapper around EndpointQuery::getData().
   *
   * @param array $placeholders
   *   Optional placeholders array to be used for the query.
   * @param array $query_args
   *   Optional query args array to be used for the query.
   *
   * @return object|array
   *   The result from the endpoint query.
   */
  public function getData(array $placeholders = [], array $query_args = []);

  /**
   * Wrapper around EndpointQuery::setPlaceholder().
   */
  public function setPlaceholder($key, $value);

  /**
   * Wrapper around EndpointQuery::getPlaceholder().
   */
  public function getPlaceholder($key);

  /**
   * Wrapper around EndpointQuery::getPlaceholders().
   */
  public function getPlaceholders();

  /**
   * Wrapper around EndpointQuery::getFullEndpointUrl().
   *
   * @return string
   *   A string representing the full url, including protocol and query string.
   */
  public function getFullEndpointUrl();

}
