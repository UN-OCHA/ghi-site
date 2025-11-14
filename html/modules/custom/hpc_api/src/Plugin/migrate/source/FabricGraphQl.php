<?php

namespace Drupal\hpc_api\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ghi_plans\Plugin\FabricQuery\Interfaces\ImportQueryInterface;
use Drupal\hpc_api\Query\FabricQueryManager;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_plus\DataParserPluginInterface;
use Drupal\migrate_plus\DataParserPluginManager;
use Drupal\migrate_plus\Plugin\migrate\source\SourcePluginExtension;

/**
 * Source plugin for retrieving data from the fabric graphql instance.
 *
 * @MigrateSource(
 *   id = "hpc_fabric_graphql"
 * )
 */
class FabricGraphQl extends SourcePluginExtension implements ContainerFactoryPluginInterface {

  /**
   * The fabric query manager.
   *
   * @var \Drupal\hpc_api\Query\FabricQueryManager
   */
  protected $fabricQueryManager;

  /**
   * List of source endpoint definitions.
   *
   * @var array
   */
  protected $fabricQuery;

  /**
   * The data parser plugin.
   *
   * @var \Drupal\migrate_plus\DataParserPluginInterface
   */
  protected DataParserPluginInterface $dataParserPlugin;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, FabricQueryManager $fabric_query_manager, protected DataParserPluginManager $parserPluginManager,) {
    $this->fabricQueryManager = $fabric_query_manager;
    $source_configuration = $migration->getSourceConfiguration();
    $cache_base_time = $source_configuration['cache_base_time'] ?? NULL;
    $configuration['cache_base_time'] = $cache_base_time;
    $configuration['cache_prefix'] = $migration->id();

    $query = $configuration['fabric_query'];
    try {
      $query_handler = $this->fabricQueryManager->createInstance($query['plugin']);
      if ($query_handler instanceof ImportQueryInterface) {
        $this->fabricQuery = $query;
      }
    }
    catch (\Exception $e) {
      // Invalid plugin, fail silently.
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create($container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    /** @var self */
    $instance = new static($configuration, $plugin_id, $plugin_definition, $migration, $container->get('plugin.manager.fabric_query_manager'), $container->get('plugin.manager.migrate_plus.data_parser'));
    return $instance;
  }

  /**
   * Return a string representing the source.
   *
   * @return string
   *   The source plugins and method being used.
   */
  public function __toString(): string {
    $query_handler = $this->fabricQueryManager->createInstance($this->fabricQuery['plugin']);
    $urls = get_class($query_handler);
    return $urls;
  }

  /**
   * Returns the initialized data parser plugin.
   *
   *   The data parser plugin.
   */
  public function getDataParserPlugin(): DataParserPluginInterface {
    if (!isset($this->dataParserPlugin)) {
      $this->dataParserPlugin = $this->parserPluginManager->createInstance($this->configuration['data_parser_plugin'], $this->configuration);
    }
    return $this->dataParserPlugin;
  }

  /**
   * Creates and returns a filtered Iterator over the documents.
   *
   *   An iterator over the documents providing source rows that match the
   *   configured item_selector.
   */
  protected function initializeIterator(): DataParserPluginInterface {
    return $this->getDataParserPlugin();
  }

}
