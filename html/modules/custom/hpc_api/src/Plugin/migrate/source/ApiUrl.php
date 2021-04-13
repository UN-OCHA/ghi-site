<?php

namespace Drupal\hpc_api\Plugin\migrate\source;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_plus\Plugin\migrate\source\Url;

/**
 * Source plugin for retrieving data via URLs.
 *
 * @MigrateSource(
 *   id = "hpc_api_url"
 * )
 */
class ApiUrl extends Url {

  /**
   * List of source endpoint definitions.
   *
   * @var array
   */
  protected $endpoints;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    $this->endpoints = $configuration['endpoints'];
    foreach ($this->endpoints as $endpoint) {
      $query_handler = \Drupal::service('hpc_api.endpoint_query');
      $query_handler->setArguments(is_array($endpoint) ? $endpoint : ['endpoint' => $endpoint]);
      $configuration['urls'][] = $query_handler->getFullEndpointUrl();
      $configuration['auth_headers'] = $query_handler->getAuthHeaders();
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

}
