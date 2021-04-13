<?php

namespace Drupal\hpc_api\Plugin\migrate_plus\data_fetcher;

use Drupal\migrate\MigrateException;
use GuzzleHttp\Exception\RequestException;
use Drupal\migrate_plus\Plugin\migrate_plus\data_fetcher\Http;

use Drupal\hpc_api\Helpers\QueryHelper;

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
class ApiHttp extends Http {

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
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    $this->paged = !empty($configuration['paged']);
    $this->newStructure = !empty($configuration['new_structure']);
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse($url) {
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
  public function getResponseContent($url) {
    $import_file = $this->getImportFileName($url);
    if (!file_exists($import_file)) {
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
    return rtrim(QueryHelper::IMPORT_DIR, '/') . '/' . $file_name;
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

    // Write the data to the filesystem.
    file_put_contents($import_file, json_encode(['data' => $data]));
  }

}
