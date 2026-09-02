<?php

namespace Drupal\hpc_api\Plugin\migrate_plus\data_parser;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hpc_api\Plugin\migrate_plus\data_fetcher\FabricHttp;
use Drupal\hpc_api\Query\FabricQueryPluginInterface;
use Drupal\migrate_plus\Attribute\DataParser;
use Drupal\migrate_plus\DataFetcherPluginManager;
use Drupal\migrate_plus\Plugin\migrate_plus\data_parser\Json;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Obtain JSON data for migration.
 */
#[DataParser(
  id: 'fabric_graphql',
  title: new TranslatableMarkup('Fabric GraphQL')
)]
class FabricGraphQl extends Json {

  /**
   * Iterator over the JSON data.
   */
  protected ?\ArrayIterator $iterator = NULL;

  /**
   * The source query plugin.
   *
   * @var \Drupal\hpc_api\Query\FabricQueryPluginInterface
   */
  protected FabricQueryPluginInterface $sourceQuery;

  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, protected DataFetcherPluginManager $fetcherPluginManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $fetcherPluginManager);
    $this->urls = $configuration['urls'];
    $this->itemSelector = $configuration['item_selector'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    /** @var \Drupal\hpc_api\Query\FabricCLient $fabric_client */
    $fabric_client = $container->get('hpc_api.fabric_client');
    $configuration['urls'] = [$fabric_client->getEndpointUrl()];
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.migrate_plus.data_fetcher'),
    );
    $instance->sourceQuery = $container->get('plugin.manager.fabric_query_manager')->createInstance($configuration['fabric_query']['plugin'], $configuration);
    return $instance;
  }

  /**
   * Retrieves the JSON data and returns it as an array.
   *
   * @param string $url
   *   URL of a JSON feed.
   * @param string|int $item_selector
   *   Selector within the data content at which useful data is found.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   */
  protected function getSourceData(string $url, string|int $item_selector = '') {
    $data_fetcher_plugin = $this->getDataFetcherPlugin();
    if (!$data_fetcher_plugin instanceof FabricHttp) {
      return parent::getSourceData($url, $item_selector);
    }
    return $data_fetcher_plugin->getSourceData();
  }

  /**
   * {@inheritdoc}
   */
  protected function openSourceUrl(string $url): bool {
    $data_fetcher_plugin = $this->getDataFetcherPlugin();
    if (!$data_fetcher_plugin instanceof FabricHttp) {
      return parent::openSourceUrl($url);
    }
    // Get the source data.
    $source_data = $data_fetcher_plugin->getSourceData();
    if (is_null($source_data)) {
      return FALSE;
    }
    $source_data = array_map(function ($item) {
      return (array) $item;
    }, $source_data);
    $this->iterator = new \ArrayIterator($source_data);
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function count(): int {
    if (!isset($this->iterator)) {
      $this->openSourceUrl($this->urls[0]);
    }
    return iterator_count($this->iterator);
  }

}
