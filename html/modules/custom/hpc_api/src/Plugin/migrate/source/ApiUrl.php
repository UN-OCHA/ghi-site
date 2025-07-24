<?php

namespace Drupal\hpc_api\Plugin\migrate\source;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_plus\DataParserPluginManager;
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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, protected DataParserPluginManager $parserPluginManager) {
    $source_configuration = $migration->getSourceConfiguration();
    $cache_base_time = $source_configuration['cache_base_time'] ?? NULL;
    $configuration['cache_base_time'] = $cache_base_time;
    $configuration['cache_prefix'] = $migration->id();

    $this->endpoints = $configuration['endpoints'];
    foreach ($this->endpoints as $endpoint) {
      /** @var \Drupal\hpc_api\Query\EndpointQuery */
      $query_handler = \Drupal::service('hpc_api.endpoint_query');
      $query_handler->setArguments(is_array($endpoint) ? $endpoint : ['endpoint' => $endpoint]);
      $query_handler->setCacheBaseTime($cache_base_time);
      $configuration['urls'][] = $query_handler->getFullEndpointUrl();
      $configuration['auth_headers'] = $query_handler->getAuthHeaders();
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $parserPluginManager);
  }

}
