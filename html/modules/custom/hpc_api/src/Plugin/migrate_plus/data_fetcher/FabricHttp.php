<?php

namespace Drupal\hpc_api\Plugin\migrate_plus\data_fetcher;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\hpc_api\Query\ImportQueryInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate_plus\Plugin\migrate_plus\data_fetcher\Http;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Retrieve data over an HTTP connection for migration.
 *
 * Example:
 *
 * @code
 * source:
 *   plugin: hpc_fabric_graphql
 *   data_fetcher_plugin: fabric_http
 *   headers:
 *     Accept: application/json
 *     User-Agent: Internet Explorer 6
 *     Authorization-Key: secret
 *     Arbitrary-Header: foobarbaz
 * @endcode
 *
 * @DataFetcher(
 *   id = "fabric_http",
 *   title = @Translation("Fabric HTTP")
 * )
 */
class FabricHttp extends Http implements ContainerFactoryPluginInterface {

  /**
   * The endpoint query to retrieve API data.
   *
   * @var \Drupal\hpc_api\Query\FabricQueryManager
   */
  protected $fabricQueryManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    /** @var \Drupal\hpc_api\Query\FabricQueryManager $fabric_query_manager */
    $fabric_query_manager = $container->get('plugin.manager.fabric_query_manager');
    $configuration['url'] = $fabric_query_manager->getEndpointUrl();
    /** @var \Drupal\hpc_api\Plugin\migrate_plus\data_fetcher\FabricHttp $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fabricQueryManager = $fabric_query_manager;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse($url): ResponseInterface {
    return new Response();
  }

  /**
   * Get the source data for the migration.
   */
  public function getSourceData(): mixed {
    try {
      $query = $this->configuration['fabric_query'];
      $query_handler = NULL;
      try {
        $query_handler = $this->fabricQueryManager->createInstance($query['plugin']);
      }
      catch (\Exception $e) {
        throw new MigrateException('Error message: ' . $e->getMessage());
      }

      if (!$query_handler instanceof ImportQueryInterface) {
        throw new MigrateException('Invalid fabric query plugin.');
      }
      $source_data = $query_handler->getSourceData();
      if (empty($source_data)) {
        throw new MigrateException('Source data is empty.');
      }
    }
    catch (RequestException $e) {
      throw new MigrateException('Error message: ' . $e->getMessage());
    }
    return $source_data;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponseContent($url): string {
    return '';
  }

}
