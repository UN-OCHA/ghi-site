<?php

namespace Drupal\hpc_api\Plugin\migrate_plus\data_fetcher;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\hpc_api\Helpers\ApiEntityHelper;
use Drupal\hpc_api\Helpers\QueryHelper;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\migrate\MigrateException;
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
  protected $endpointQuery;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    /** @var \Drupal\hpc_api\Plugin\migrate_plus\data_fetcher\ApiHttp $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->endpointQuery = $container->get('hpc_api.endpoint_query');
    return $instance;
  }

  /**
   * If the response is paged ot not.
   *
   * @return bool
   *   TRUE if the response is paged, FALSE otherwise.
   */
  private function isPaged() {
    return !empty($this->configuration['paged']);
  }

  /**
   * If the HPC endpoint uses the new or the old structure.
   *
   * In the new structure, everything actual is reponse is directly in the root
   * level data property, while the old structure uses an additonal "results"
   * sproperty.
   *
   * @return bool
   *   TRUE if the new structure is used, FALSE otherwise.
   */
  private function isNewStructure() {
    return !empty($this->configuration['new_structure']);
  }

  /**
   * Get the type of entity to process if set.
   *
   * @return string
   *   The type of process to apply, either "governing" or "plan".
   */
  private function getProcessEntitiesType() {
    return !empty($this->configuration['process_entities']) ? $this->configuration['process_entities'] : NULL;
  }

  /**
   * Get the cache prefix for local files.
   *
   * @return string
   *   The cache prefix to use.
   */
  private function getCachePrefix() {
    $prefix = $this->configuration['cache_prefix'] ?? NULL;
    return $prefix ? $prefix . '__' : '';
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
    return rtrim(QueryHelper::IMPORT_DIR, '/') . '/' . $this->getCachePrefix() . $file_name;
  }

  /**
   * Download the source into a local cache file.
   */
  private function downloadSource($url) {
    $import_file = $this->getImportFileName($url);
    // Rebuild the source file.
    if ($this->isPaged()) {
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

        if (!$this->isNewStructure()) {
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
      if (!$this->isNewStructure()) {
        $data = $body['data'];
      }
      else {
        $data = $body['data']['results'];
      }
    }

    if ($process_type = $this->getProcessEntitiesType()) {
      $data = $this->processEntities($data, $process_type);
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
