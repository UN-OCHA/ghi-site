<?php

namespace Drupal\ghi_content\RemoteSource;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ghi_content\RemoteContent\HpcContentModule\RemoteArticle;
use Drupal\ghi_content\RemoteContent\HpcContentModule\RemoteParagraph;
use Drupal\ghi_content\RemoteContent\RemoteParagraphInterface;
use Drupal\ghi_content\RemoteResponse\RemoteResponse;
use Drupal\hpc_api\Traits\SimpleCacheTrait;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

/**
 * HPC Content Module specific remote source base class.
 */
abstract class RemoteSourceBaseHpcContentModule extends RemoteSourceBase {

  use SimpleCacheTrait;

  /**
   * {@inheritdoc}
   */
  public function getArticle($id) {
    $fields = [
      'id',
      'title',
      'section',
      'summary',
      'tags',
      'created',
      'updated',
    ];
    $fields['content_space'] = [
      'title',
      'tags',
    ];
    $fields['content'] = [
      'id',
      'uuid',
      'type',
      'typeLabel',
      'promoted',
      'rendered',
      'configuration',
    ];
    $fields['image'] = [
      'credits',
      'imageUrl',
    ];
    $fields['imageCaption'] = [
      'location',
      'text',
    ];
    $article_data = $this->fetchArticleData($id, $fields);
    return $article_data ? new RemoteArticle($article_data, $this) : NULL;
  }

  /**
   * Fetch data for an article identified by $id.
   */
  private function fetchArticleData($id, array $fields) {
    $query = '{
      article(id:' . $id . ') {' . $this->getFieldString($fields) . '}
    }';

    $response = $this->query($query);
    if (!$response->has('article')) {
      return NULL;
    }
    return $response->get('article');
  }

  /**
   * {@inheritdoc}
   */
  public function getParagraph($id) {
    $fields = [
      'id',
      'uuid',
      'type',
      'typeLabel',
      'promoted',
      'rendered',
      'configuration',
    ];
    $paragraph_data = $this->fetchParagraphData($id, $fields);
    return new RemoteParagraph($paragraph_data, $this);
  }

  /**
   * Fetch data for an article identified by $id.
   */
  private function fetchParagraphData($id, array $fields) {
    $query = '{
      paragraph(id:' . $id . ') {' . $this->getFieldString($fields) . '}
    }';

    $response = $this->query($query);
    if (!$response->has('paragraph')) {
      return NULL;
    }
    return $response->get('paragraph');
  }

  /**
   * {@inheritdoc}
   */
  public function searchArticlesByTitle($title) {
    $query = '{
      articleSearch(title:"' . $title . '") {
        count
        items {
          id
        }
      }
    }';
    $response = $this->query($query);
    if (!$response->has('articleSearch') || !$response->get('articleSearch')->items) {
      return [];
    }
    return array_map(function ($item) {
      return $this->getArticle($item->id);
    }, $response->get('articleSearch')->items);
  }

  /**
   * {@inheritdoc}
   */
  public function query($payload) {
    $body = '{"query": "query ' . str_replace("\n", " ", addslashes(trim($payload))) . '"}';
    $headers = [
      'Content-type: application/json',
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
    $cache_key = $this->getCacheKey(['url' => $this->getRemoteEndpointUrl()] + $post_args);
    if (!$this->disableCache && $response = $this->cache($cache_key)) {
      // If we have a cached version, use that.
      return $response;
    }

    // Otherwise send the query.
    $response = new RemoteResponse();
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
    return $string;
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
    $response = $this->query('{connection}');
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
  public function getFileContent($uri) {
    $options = [];
    if ($basic_auth = $this->getRemoteBasicAuth()) {
      $options['http'] = [
        'method' => 'GET',
        'header' => 'Authorization: Basic ' . base64_encode($basic_auth['user'] . ':' . $basic_auth['pass']),
      ];
    }
    $context = stream_context_create($options);
    return file_get_contents($uri, FALSE, $context);
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
          $article_node = $referenced_article ? $this->articleManager->loadNodeForRemoteArticle($referenced_article) : NULL;
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
  public function importSource(array $tags = NULL) {
    $this->disableCache();
    $query = '{
      articleExport ' . ($tags !== NULL ? '(tags:["' . implode('", "', $tags) . '"])' : '') . '{
        count
        items {
          id
          title
          section
          summary
          created
          updated
        }
      }
    }';
    $response = $this->query($query);
    if (!$response->has('articleExport') || !$response->get('articleExport')->items) {
      return [];
    }
    return array_map(function ($item) {
      return (array) $item;
    }, $response->get('articleExport')->items);
  }

}
