<?php

namespace Drupal\hpc_api\Plugin\migrate_plus\data_fetcher;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\hpc_api\Helpers\ApiEntityHelper;
use Drupal\hpc_api\Helpers\QueryHelper;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\migrate\MigrateException;
use Drupal\migrate_plus\DataFetcherPluginBase;
use Drupal\migrate_plus\Plugin\migrate_plus\data_fetcher\Http;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Retrieve data over an HTTP connection for migration.
 *
 * Example:
 *
 * @code
 * source:
 *   plugin: hpc_api_url
 *   data_fetcher_plugin: hpc_api_http
 *   headers:
 *     Accept: application/json
 *     User-Agent: Internet Explorer 6
 *     Authorization-Key: secret
 *     Arbitrary-Header: foobarbaz
 * @endcode
 *
 * @DataFetcher(
 *   id = "hpc_api_http",
 *   title = @Translation("HPC API HTTP")
 * )
 */
class ApiHttp extends Http implements ContainerFactoryPluginInterface {

  /**
   * The endpoint query to retrieve API data.
   *
   * @var \Drupal\hpc_api\Query\EndpointQuery
   */
  private $endpointQuery;

  /**
   * If the request should be paged or not.
   *
   * @var bool
   */
  private $paged;

  /**
   * If the HPC endpoint uses the new or the old structure.
   *
   * In the new structure, everything actual is reponse is directly in the root
   * level data property, while the old structure uses an additonal "results"
   * sproperty.
   *
   * @var bool
   */
  private $newStructure;

  /**
   * Optional filters to apply to the source data.
   *
   * Currently only supports a 'start_year' key.
   *
   * @var array
   */
  private $filter;

  /**
   * Optional entities process logic to apply to the source data.
   *
   * @var array
   */
  private $processEntities;

  /**
   * Cache prefix for local files.
   *
   * @var string
   */
  private $cachePrefix;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EndpointQuery $endpoint_query) {
    $this->endpointQuery = $endpoint_query;
    $this->paged = !empty($configuration['paged']);
    $this->newStructure = !empty($configuration['new_structure']);
    $this->filter = !empty($configuration['filter']) ? $configuration['filter'] : NULL;
    $this->processEntities = !empty($configuration['process_entities']) ? $configuration['process_entities'] : NULL;
    $this->cachePrefix = $configuration['cache_prefix'] ?? NULL;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): DataFetcherPluginBase {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('hpc_api.endpoint_query')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse($url): ResponseInterface {
    try {
      $options = [
        'headers' => $this->getRequestHeaders(),
        'timeout' => 300,
      ];
      if (!empty($this->configuration['auth_headers'])) {
        $options['headers'] = array_merge($options['headers'], $this->configuration['auth_headers']);
      }
      $response = $this->httpClient->get($url, $options);
      if (empty($response)) {
        throw new MigrateException('No response at ' . $url . '.');
      }
    }
    catch (RequestException $e) {
      throw new MigrateException('Error message: ' . $e->getMessage() . ' at ' . $url . '.');
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponseContent($url): string {
    $import_file = $this->getImportFileName($url);
    if (!file_exists($import_file) || PHP_SAPI === 'cli') {
      // If the file does not yet exist, download it.
      $this->downloadSource($url);
    }
    // Now the file should exist.
    if (file_exists($import_file)) {
      return file_get_contents($import_file);
    }
    // Fallback.
    return $this->getResponse($url)->getBody();
  }

  /**
   * Get the filenname of the local cache file.
   */
  private function getImportFileName($url) {
    $_url = str_replace('https://', '', $url);
    $_url = str_replace('http://', '', $_url);
    $_url = str_replace('/', '_', $_url);
    $_url = str_replace('?', '_', $_url);
    $_url = str_replace('&', '_', $_url);
    $file_name = $_url . '.json';
    return rtrim(QueryHelper::IMPORT_DIR, '/') . '/' . ($this->cachePrefix ? $this->cachePrefix . '__' : '') . $file_name;
  }

  /**
   * Download the source into a local cache file.
   */
  private function downloadSource($url) {
    $import_file = $this->getImportFileName($url);
    // Rebuild the source file.
    if ($this->paged) {
      // Get the data from the paged source in multiple calls.
      $data = [];
      $page = 0;
      while (1 == 1) {
        $response = $this->getResponse($url . '&limit=200&page=' . $page);
        if ($response->getStatusCode() != 200) {
          return;
        }
        $body = json_decode($response->getBody(), TRUE);
        if (!$body) {
          return;
        }

        if (!$this->newStructure) {
          if (!count($body)) {
            // No more data, leave the loop.
            break;
          }
          $data = array_merge($data, $body['data']);
        }
        else {
          if (!count($body['results'])) {
            // No more data, leave the loop.
            break;
          }
          $data = array_merge($data, $body['data']['results']);
        }
        $page += 1;
      }
    }
    else {
      // Get the data in a single call.
      $response = $this->getResponse($url);
      if ($response->getStatusCode() != 200) {
        return;
      }

      $body = json_decode($response->getBody(), TRUE);
      if (!$body) {
        return;
      }
      if (!$this->newStructure) {
        $data = $body['data'];
      }
      else {
        $data = $body['data']['results'];
      }
    }

    if (!empty($this->processEntities)) {
      $data = $this->processEntities($data, $this->processEntities);
    }

    // Write the data to the filesystem.
    file_put_contents($import_file, json_encode(['data' => $data]));
  }

  /**
   * Process source data for plan entities.
   *
   * @param array $data
   *   The source data for all plans.
   * @param string $type
   *   The type of plan entity to process. Can be either "plan" or "governing".
   *
   * @return array
   *   The processed plan entities.
   */
  private function processEntities(array $data, $type) {
    $entities = [];
    if (!in_array($type, ['plan', 'governing'])) {
      return $entities;
    }

    $endpoint_args = [
      'api_version' => 'v2',
      'auth_method' => EndpointQuery::AUTH_METHOD_API_KEY,
      'query_args' => [
        'content' => 'entities',
        'disaggregation' => 'false',
      ],
      'cache_base_time' => $this->configuration['cache_base_time'] ?? NULL,
    ];

    ini_set('memory_limit', '512M');
    set_time_limit(0);
    foreach ($data as $item) {
      $has_published_version = FALSE;
      if (!empty($item['planTags'])) {
        $published_versions = array_filter($item['planTags'], function ($version) {
          return $version['public'] == TRUE;
        });
        $has_published_version = !empty($published_versions);
      }

      $this->endpointQuery->setArguments($endpoint_args);
      $this->endpointQuery->setEndpoint('plan/' . $item['id']);

      if ($has_published_version) {
        // If we have a public plan version, let's fetch it's entities.
        $this->endpointQuery->setEndpointArgument('version', 'current');
      }
      $plan_data = $this->endpointQuery->getData();
      if (!$plan_data) {
        continue;
      }
      $_entities = ApiEntityHelper::getProcessedPlanEntitesByType($plan_data, $type);
      $entities = array_merge($entities, $_entities);
    }

    return $entities;
  }

}
