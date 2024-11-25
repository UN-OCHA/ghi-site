<?php

namespace Drupal\ghi_content\RemoteSource;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ghi_content\RemoteContent\HpcContentModule\RemoteArticle;
use Drupal\ghi_content\RemoteContent\HpcContentModule\RemoteDocument;
use Drupal\ghi_content\RemoteContent\HpcContentModule\RemoteParagraph;
use Drupal\ghi_content\RemoteContent\RemoteParagraphInterface;
use Drupal\ghi_content\RemoteResponse\RemoteResponse;
use Drupal\hpc_api\Traits\SimpleCacheTrait;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\RequestOptions;

/**
 * HPC Content Module specific remote source base class.
 */
abstract class RemoteSourceBaseHpcContentModule extends RemoteSourceBase {

  use SimpleCacheTrait;

  /**
   * Fetch data from a query.
   *
   * @param string $query_name
   *   The name of the graphql query.
   * @param array $arguments
   *   A set of arguments as key value pairs. Can be empty.
   * @param array $fields
   *   An set of fields.
   *
   * @return mixed
   *   The resuklt of the query. Most often an object.
   */
  private function fetchData($query_name, array $arguments, array $fields) {
    $argument_string = $this->getArgumentString($arguments);
    $field_string = $this->getFieldString($fields);
    $response = $this->query("{ $query_name $argument_string { $field_string }}");
    if (!$response->has($query_name)) {
      return NULL;
    }
    return $response->get($query_name);
  }

  /**
   * {@inheritdoc}
   */
  public function getDocument($id) {
    $fields = [
      'id',
      'title',
      'title_short',
      'summary',
      'tags',
      'created',
      'updated',
    ];
    $fields['content_space'] = [
      'id',
      'title',
      'tags',
    ];
    $fields['chapters'] = [
      'id',
      'uuid',
      'title',
      'title_short',
      'summary',
      'hidden',
    ];
    $fields['chapters']['articles'] = [
      'id',
    ];
    $fields['image'] = [
      'credits',
      'imageUrl',
    ];
    $fields['imageCaption'] = [
      'location',
      'text',
    ];
    $document_data = $this->fetchData('document', ['id' => $id], $fields);
    return $document_data ? new RemoteDocument($document_data, $this) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getArticle($id, $rendered = TRUE) {
    $fields = [
      'id',
      'title',
      'title_short',
      'summary',
      'tags',
      'created',
      'updated',
    ];
    $fields['content_space'] = [
      'id',
      'title',
      'tags',
    ];
    $fields['content'] = array_filter([
      'id',
      'uuid',
      'type',
      'typeLabel',
      'promoted',
      $rendered ? 'rendered' : NULL,
      'configuration',
    ]);
    $fields['image'] = [
      'credits',
      'imageUrl',
    ];
    $fields['imageCaption'] = [
      'location',
      'text',
    ];
    $article_data = $this->fetchData('article', ['id' => $id], $fields);
    return $article_data ? new RemoteArticle($article_data, $this) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getParagraph($id, $rendered = TRUE) {
    $fields = array_filter([
      'id',
      'uuid',
      'type',
      'typeLabel',
      'promoted',
      $rendered ? 'rendered' : NULL,
      'configuration',
    ]);
    $paragraph_data = $this->fetchData('paragraph', ['id' => $id], $fields);
    return new RemoteParagraph($paragraph_data, $this);
  }

  /**
   * {@inheritdoc}
   */
  public function searchDocumentsByTitle($title) {
    $query = '{
      documentSearch(title:"' . $title . '") {
        count
        metaData {
          id
          title
        }
      }
    }';
    $response = $this->query($query);
    if (!$response->has('documentSearch') || !$response->get('documentSearch')->metaData) {
      return [];
    }
    return array_filter(array_map(function ($item) {
      return $this->getDocument($item->id);
    }, $response->get('documentSearch')->metaData));
  }

  /**
   * {@inheritdoc}
   */
  public function searchArticlesByTitle($title) {
    $query = '{
      articleSearch(title:"' . $title . '") {
        count
        metaData {
          id
          title
        }
      }
    }';
    $response = $this->query($query);
    if (!$response->has('articleSearch') || !$response->get('articleSearch')->metaData) {
      return [];
    }
    return array_filter(array_map(function ($item) {
      return new RemoteArticle($item, $this);
    }, $response->get('articleSearch')->metaData));
  }

  /**
   * {@inheritdoc}
   */
  public function query($payload) {
    $query = 'query ' . str_replace("\n", " ", addslashes(trim($payload)));
    $body = '{"query": "' . $query . '"}';

    $headers = [
      'Content-type' => 'application/json',
      'Apollo-Require-Preflight' => 'true',
    ];
    if ($basic_auth = $this->getRemoteBasicAuth()) {
      $headers['Authorization'] = 'Basic ' . base64_encode($basic_auth['user'] . ':' . $basic_auth['pass']);
    }
    if ($hid_user_id = $this->hidUserData->getId()) {
      $headers['hid-user'] = $hid_user_id;
    }

    $cookies = ['access_key' => $this->getRemoteAccessKey()];
    $jar = CookieJar::fromArray($cookies, parse_url($this->getRemoteBaseUrl(), PHP_URL_HOST));
    $post_args = [
      'body' => $body,
      'headers' => $headers,
      'cookies' => $jar,
    ];

    // See if we have a cached version already for this request.
    $cache_key = $this->getCacheKey(['url' => $this->getRemoteEndpointUrl()] + ['body' => $post_args['body']]);
    if (!$this->disableCache && $response = $this->cache($cache_key, NULL, FALSE, $this->cacheBaseTime ?? NULL)) {
      // If we have a cached version, use that.
      return $response;
    }

    // Otherwise send the query.
    $response = new RemoteResponse();
    $result = NULL;
    try {
      $result = $this->httpClient->post($this->getRemoteEndpointUrl(), $post_args);
    }
    catch (ClientException $e) {
      $response->setCode($e->getCode());
      return $response;
    }
    catch (ServerException $e) {
      $response->setCode($e->getCode());
      return $response;
    }
    catch (\Exception $e) {
      // Just fail silently.
    }

    if (!$result || $result->getStatusCode() !== 200) {
      $response->setCode($result ? $result->getStatusCode() : 500);
      return $response;
    }
    try {
      $body_data = json_decode((string) $result->getBody());
      $response->setCode($result ? $result->getStatusCode() : 500);
      $response->setData(is_object($body_data) && property_exists($body_data, 'data') ? $body_data->data : NULL);
    }
    catch (\Exception $e) {
      // Just catch it for the moment.
    }
    // Store the response in the cache.
    $this->cache($cache_key, $response);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function changeRessourceLinks($string) {
    $base_url = $this->getRemoteBaseUrl();
    $string = str_replace('"/themes/custom', '"' . $base_url . '/themes/custom', $string);
    $string = str_replace('"/sites/default/files', '"' . $base_url . '/sites/default/files', $string);
    $string = str_replace(' /sites/default/files', $base_url . '/sites/default/files', $string);
    $string = str_replace('"/media/oembed', '"' . $base_url . '/media/oembed', $string);
    return $string;
  }

  /**
   * Transform an arguments array into a string for use in a GraphQL query.
   *
   * @param array $arguments
   *   The input array.
   *
   * @return string
   *   The resulting string.
   */
  private function getArgumentString(array $arguments) {
    if (empty($arguments)) {
      return '';
    }
    $argument_string = implode(',', array_map(function ($key, $value) {
      return "$key:$value";
    }, array_keys($arguments), array_values($arguments)));
    return $argument_string ? '(' . $argument_string . ')' : '';
  }

  /**
   * Transform a fields array into a string for use in a GraphQL query.
   *
   * @param array $fields
   *   The input array.
   *
   * @return string
   *   The resulting string.
   */
  private function getFieldString(array $fields) {
    $string = [];
    foreach ($fields as $key => $field) {
      if (!is_array($field)) {
        $string[] = $field;
      }
      else {
        $string[] = $key . ' {' . $this->getFieldString($field) . '}';
      }
    }
    return implode(' ', $string);
  }

  /**
   * Get the base url of the remote source.
   */
  private function getRemoteBaseUrl() {
    $config = $this->getConfiguration();
    return rtrim($config['base_url'], '/');
  }

  /**
   * Get the endpoint of the remote source.
   */
  private function getRemoteEndpoint() {
    $config = $this->getConfiguration();
    return trim($config['endpoint'], '/');
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteEndpointUrl() {
    return $this->getRemoteBaseUrl() . '/' . $this->getRemoteEndpoint();
  }

  /**
   * Get the base url of the remote source.
   */
  private function getRemoteAccessKey() {
    $config = $this->getConfiguration();
    return $config['access_key'] ?? NULL;
  }

  /**
   * Get the basic auth settings for the remote source.
   */
  private function getRemoteBasicAuth() {
    $config = $this->getConfiguration();
    return $config['basic_auth'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function checkConnection() {
    try {
      $response = $this->query('{connection}');
    }
    catch (\Exception $e) {
      return FALSE;
    }
    return $response && $response->getStatus() && $response->has('connection') && $response->get('connection') == 'connected';
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Base URL'),
      '#description' => $this->t('Enter the base url for this remote source'),
      '#default_value' => $this->getRemoteBaseUrl(),
      '#required' => TRUE,
    ];
    $form['endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Endpoint'),
      '#description' => $this->t('Enter the endpoint for this remote source'),
      '#default_value' => $this->getRemoteEndpoint(),
      '#required' => TRUE,
    ];
    $form['access_key'] = [
      '#type' => 'password',
      '#title' => $this->t('Access key'),
      '#description' => $this->t('Enter the access key for this remote source'),
      '#default_value' => $this->getRemoteAccessKey(),
    ];

    if (!empty($this->getRemoteAccessKey())) {
      $form['access_key']['#description'] .= '<br />' . $this->t('<em>Note:</em> An access key is already set. You can set a new one, or leave this field empty to keep the current one.');
    }

    $basic_auth = $this->getRemoteBasicAuth();
    $form['basic_auth'] = [
      '#type' => 'details',
      '#title' => $this->t('Basic auth'),
      '#open' => !empty($basic_auth),
      '#tree' => TRUE,
    ];
    $form['basic_auth']['user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#description' => $this->t('Enter the basic auth username'),
      '#default_value' => $basic_auth['user'] ?? NULL,
    ];
    $form['basic_auth']['pass'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#description' => $this->t('Enter the basic auth password'),
      '#default_value' => $basic_auth['pass'] ?? NULL,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    if (empty($configuration['access_key']) && !empty($this->getRemoteAccessKey())) {
      $configuration['access_key'] = $this->getRemoteAccessKey();
    }
    parent::setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getContentUrl($id, $type = 'canonical') {
    if ($type == 'edit') {
      return Url::fromUri($this->getRemoteBaseUrl() . '/node/' . $id . '/edit');
    }
    return Url::fromUri($this->getRemoteBaseUrl() . '/node/' . $id);
  }

  /**
   * {@inheritdoc}
   */
  public function getFileSize($uri) {
    if ($basic_auth = $this->getRemoteBasicAuth()) {
      $options[RequestOptions::AUTH] = [
        $basic_auth['user'],
        $basic_auth['pass'],
      ];
    }
    try {
      $response = $this->httpClient->head($uri, $options);
    }
    catch (GuzzleException $e) {
      return NULL;
    }
    return $response->getHeader('content-length') ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFileContent($uri) {
    $options = [];
    if ($basic_auth = $this->getRemoteBasicAuth()) {
      $options[RequestOptions::AUTH] = [
        $basic_auth['user'],
        $basic_auth['pass'],
      ];
    }
    $response = $this->httpClient->get($uri, $options);
    return $response->getBody();
  }

  /**
   * {@inheritdoc}
   */
  public function getLinkMap(RemoteParagraphInterface $paragraph) {
    $link_map = [];
    if ($paragraph->getType() == 'article_list') {
      foreach ($paragraph->getConfiguration()['links'] ?? [] as $link_item) {
        // Look up the referenced item. We only support canoncical entity links
        // for the moment.
        if ($link_item['route_name'] == 'entity.node.canonical') {
          $node_id = $link_item['route_parameters']['node'] ?? NULL;
          $referenced_article = $node_id ? $paragraph->getSource()->getArticle($node_id) : NULL;
          $article_node = $referenced_article ? $this->articleManager->loadNodeForRemoteContent($referenced_article) : NULL;
          if ($article_node && $article_node->access('view')) {
            $link_map[$link_item['alias']] = $article_node->toUrl()->toString();
          }
        }
      }
      if (!empty($link_map)) {
        uksort($link_map, function ($_a, $_b) {
          return strlen($_a) - strlen($_b);
        });
        $link_map = array_reverse($link_map, TRUE);
      }
    }
    return $link_map;
  }

  /**
   * {@inheritdoc}
   */
  public function getImportIds($type, ?array $tags) {
    $query_name = match ($type) {
      'article' => 'articleExport',
      'document' => 'documentExport',
    };
    $query = '{
      ' . $query_name . ' ' . ($tags !== NULL ? '(tags:["' . implode('", "', $tags) . '"])' : '') . '{
        count
        ids
      }
    }';
    $response = $this->query($query);
    if (!$response->has($query_name) || !$response->get($query_name)->count) {
      return [];
    }
    return $response->get($query_name)->ids;
  }

  /**
   * {@inheritdoc}
   */
  public function getImportMetaData($type, ?array $tags) {
    $query_name = match ($type) {
      'article' => 'articleExport',
      'document' => 'documentExport',
    };
    $query = '{
      ' . $query_name . ' ' . ($tags !== NULL ? '(tags:["' . implode('", "', $tags) . '"])' : '') . '{
        count
        metaData {
          id
          title
          title_short
          summary
          content_space
          tags
          created
          updated
          status
          autoVisible
          forceUpdate
        }
      }
    }';
    $response = $this->query($query);
    if (!$response->has($query_name) || !$response->get($query_name)->count) {
      return [];
    }
    return array_map(function ($item) {
      return (array) $item;
    }, $response->get($query_name)->metaData);
  }

  /**
   * {@inheritdoc}
   */
  public function getImportData($type, $id) {
    $fields = [
      'id',
      'title',
      'title_short',
      'summary',
      'created',
      'updated',
      'status',
      'autoVisible',
      'forceUpdate',
    ];
    return (array) $this->fetchData($type, ['id' => $id], $fields);
  }

}
