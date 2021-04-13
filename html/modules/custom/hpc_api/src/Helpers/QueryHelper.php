<?php

namespace Drupal\hpc_api\Helpers;

use Drupal\hpc_api\Query\EndpointQuery;

/**
 * Helper class for API queries.
 */
class QueryHelper {

  const IMPORT_DIR = 'public://imports';

  /**
   * Get a fully instantiated query handler.
   *
   * @param string $endpoint
   *   The API endpoint URI.
   * @param array $query_args
   *   An array of query arguments.
   * @param string $api_version
   *   The API version to use, either, v1, v2, ...
   * @param string $order_by
   *   An optional property of the API response to sort by.
   * @param string $sort
   *   The sort direction, asc or desc.
   * @param string $sort_method
   *   The sort strategy.
   * @param string $auth_method
   *   The authorization method.
   *
   * @return \Drupal\hpc_api\Query\EndpointQuery
   *   An instance of the EndpointQuery class.
   */
  public static function getEndpointQueryHandler($endpoint, array $query_args = [], $api_version = 'v1', $order_by = NULL, $sort = EndpointQuery::SORT_ASC, $sort_method = EndpointQuery::SORT_METHOD_NUMERIC, $auth_method = EndpointQuery::AUTH_METHOD_BASIC) {
    $query_handler = \Drupal::service('hpc_api.endpoint_query');
    if (!$query_handler) {
      return NULL;
    }
    $arguments = [
      'endpoint' => $endpoint,
      'api_version' => $api_version,
      'auth_method' => $auth_method,
    ];
    if ($query_args) {
      $arguments['query_args'] = $query_args;
    }
    if ($order_by) {
      $arguments['order_by'] = $order_by;
      $arguments['sort'] = $sort;
      $arguments['sort_method'] = $sort_method;
    }

    $query_handler->setArguments($arguments);
    return $query_handler;
  }

  /**
   * Query the HPC API for data.
   *
   * @param string $endpoint
   *   The API endpoint URI.
   * @param array $query_args
   *   An array of query arguments.
   * @param string $api_version
   *   The API version to use, either, v1, v2, ...
   * @param string $order_by
   *   An optional property of the API response to sort by.
   * @param string $sort
   *   The sort direction, asc or desc.
   * @param string $sort_method
   *   The sort strategy.
   * @param string $auth_method
   *   The authorization method.
   *
   * @return mixed
   *   The result of the endpoint query..
   */
  public static function queryEndpoint($endpoint, array $query_args = [], $api_version = 'v1', $order_by = NULL, $sort = EndpointQuery::SORT_ASC, $sort_method = EndpointQuery::SORT_METHOD_NUMERIC, $auth_method = EndpointQuery::AUTH_METHOD_BASIC) {
    $query_handler = self::getEndpointQueryHandler($endpoint, $query_args, $api_version, $order_by, $sort, $sort_method, $auth_method);
    return $query_handler->getData();
  }

  /**
   * Static call time storage for API requests.
   *
   * @param string $endpoint_url
   *   The full API url.
   * @param float $processing_time
   *   The processing time for the given endpoint URL.
   */
  public static function endpointCallTimeStorage($endpoint_url = NULL, $processing_time = NULL) {
    $call_times = &drupal_static(__FUNCTION__);
    if ($endpoint_url === NULL) {
      return $call_times;
    }
    if ($processing_time === NULL) {
      return !empty($call_times[$endpoint_url]) ? $call_times[$endpoint_url] : NULL;
    }
    $call_times[$endpoint_url] = $processing_time;
  }

}
