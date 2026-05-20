<?php

namespace Drupal\ghi_content\RemoteSource;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ghi_content\RemoteContent\HpcContentModule\RemoteArticle;
use Drupal\ghi_content\RemoteContent\HpcContentModule\RemoteDocument;
use Drupal\ghi_content\RemoteContent\HpcContentModule\RemoteParagraph;
use Drupal\ghi_content\RemoteContent\HpcContentModule\RemoteTag;
use Drupal\ghi_content\RemoteContent\RemoteParagraphInterface;
use Drupal\ghi_content\RemoteResponse\RemoteResponse;
use Drupal\hpc_api\Traits\SimpleCacheTrait;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\RequestOptions;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * HPC Content Module specific remote source base class.
 */
abstract class RemoteSourceBaseHpcContentModule extends RemoteSourceBase implements RemoteRefreshSourceInterface {

  /**
   * Remote refresh setting keys stored in plugin configuration.
   */
  const REMOTE_REFRESH_SETTING_KEYS = [
    'webhook_secret',
    'signature_ttl',
    'max_body_size',
  ];

  use SimpleCacheTrait;

  /**
   * Log identifier for log information relating to the Content Module.
   */
  const LOG_ID = 'hpc_content';

  /**
   * The logger factory service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->loggerFactory = $container->get('logger.factory');
    return $instance;
  }

  /**
   * Fetch data from a query.
   *
   * @param string $query_name
   *   The name of the graphql query.
   * @param array $arguments
   *   A set of arguments as key value pairs. Can be empty.
   * @param array $fields
   *   An set of fields.
   * @param array $cache_tags
   *   An array of cache tags.
   *
   * @return mixed
   *   The resuklt of the query. Most often an object.
   */
  private function fetchData($query_name, array $arguments, array $fields, array $cache_tags = []) {
    $argument_string = $this->getArgumentString($arguments);
    $field_string = $this->getFieldString($fields);

    $response = $this->query("{ $query_name $argument_string { $field_string }}", $cache_tags);
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
    $cache_tags = [$this->getPluginId() . ':document:' . $id];
    $document_data = $this->fetchData('document', ['id' => $id], $fields, $cache_tags);
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
    $fields['documents'] = [
      'id',
      'title',
    ];
    $fields['image'] = [
      'credits',
      'imageUrl',
    ];
    $fields['imageCaption'] = [
      'location',
      'text',
    ];
    $cache_tags = [$this->getPluginId() . ':article:' . $id];
    $article_data = $this->fetchData('article', ['id' => $id], $fields, $cache_tags);
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
  public function getTag($name) {
    $fields = array_filter([
      'id',
      'type',
      'name',
    ]);
    $tag_data = $this->fetchData('tag', ['name' => '"' . $name . '"'], $fields);
    return new RemoteTag($tag_data, $this);
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
  public function query($payload, array $cache_tags = []) {
    $query = 'query ' . str_replace("\n", " ", addslashes(trim($payload)));
    $body = '{"query": "' . $query . '"}';

    $headers = [
      'Content-type' => 'application/json',
      'Apollo-Require-Preflight' => 'true',
    ];
    if ($basic_auth = $this->getRemoteBasicAuth()) {
      $headers['Authorization'] = 'Basic ' . base64_encode($basic_auth['user'] . ':' . $basic_auth['pass']);
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
      $this->logError($e->getMessage());
      $response->setCode($e->getCode());
      return $response;
    }
    catch (ServerException $e) {
      $this->logError($e->getMessage());
      $response->setCode($e->getCode());
      return $response;
    }
    catch (\Exception $e) {
      $this->logError($e->getMessage());
      // Just fail silently and log errors.
    }

    if (!$result || $result->getStatusCode() !== Response::HTTP_OK) {
      $response->setCode($result ? $result->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR);
      return $response;
    }
    try {
      $body_data = json_decode((string) $result->getBody());
      $response_data = is_object($body_data) && property_exists($body_data, 'data') ? $body_data->data : NULL;
      $response_errors = is_object($body_data) && property_exists($body_data, 'errors') ? $body_data->errors : NULL;

      // Log errors.
      if (!empty($response_errors) && is_array($response_errors)) {
        foreach ($response_errors as $response_error) {
          if (property_exists($response_error, 'message') && is_string($response_error->message)) {
            $this->logError($response_error->message);
          }
        }
      }

      if (empty($response_data) && !empty($response_errors)) {
        // This is an error that resulted in the response data to be completely
        // empty. There are also errors that still serve data. We handle these
        // differently to limit impact for the frontend.
        $response->setCode(Response::HTTP_BAD_REQUEST);
        return $response;
      }

      $response->setCode($result ? $result->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR);
      $response->setData($response_data);
    }
    catch (\Exception $e) {
      // Just catch it for the moment and log errors.
      $this->logError($e->getMessage());
    }
    // Store the response in the cache.
    $this->cache($cache_key, $response, FALSE, NULL, $cache_tags);
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
   * {@inheritdoc}
   */
  public function getRemoteBaseUrl() {
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
  public function getRemoteRefreshWebhookSecret(): ?string {
    return $this->getRuntimeRemoteRefreshSetting('webhook_secret');
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteRefreshSignatureTtl(): int {
    return (int) $this->getRuntimeRemoteRefreshSetting('signature_ttl', 300);
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteRefreshMaxBodySize(): int {
    return (int) $this->getRuntimeRemoteRefreshSetting('max_body_size', 4096);
  }

  /**
   * Get the stored remote refresh settings for the remote source.
   */
  protected function getStoredRemoteRefreshSettings(): array {
    return $this->getConfiguration()['remote_refresh'] ?? [];
  }

  /**
   * Get the remote refresh setting value that is active at runtime.
   *
   * Values returned by the config factory include file-based overrides from
   * settings.php. Those overrides must win over the stored plugin
   * configuration because webhook validation uses the runtime configuration,
   * not necessarily the raw values visible in the remote source edit form.
   *
   * @param string $key
   *   The remote refresh setting key.
   * @param mixed $default
   *   The default value to use when neither runtime config nor stored plugin
   *   configuration provides this setting.
   *
   * @return mixed
   *   The runtime remote refresh setting value.
   */
  private function getRuntimeRemoteRefreshSetting(string $key, $default = NULL) {
    return $this->getRemoteRefreshConfigOverride($key)
      ?? ($this->getConfiguration()['remote_refresh'][$key] ?? $default);
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

    $remote_refresh = $this->getStoredRemoteRefreshSettings();
    $form['remote_refresh'] = [
      '#type' => 'details',
      '#title' => $this->t('Remote refresh'),
      '#description' => $this->t('Settings for signed refresh notifications sent by this remote source.'),
      '#open' => !empty($remote_refresh['webhook_secret']),
      '#tree' => TRUE,
    ];
    $form['remote_refresh']['endpoint'] = [
      '#type' => 'item',
      '#title' => $this->t('Refresh endpoint'),
      '#markup' => Url::fromRoute('ghi_content.remote_refresh.webhook', [], [
        'absolute' => TRUE,
      ])->toString(),
      '#input' => FALSE,
    ];
    $form['remote_refresh']['documentation'] = [
      '#type' => 'details',
      '#title' => $this->t('Webhook contract'),
      '#description' => $this->t('Use this contract when configuring the remote system that sends refresh notifications.'),
      '#open' => FALSE,
    ];
    $form['remote_refresh']['documentation']['summary'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('Send requests with the POST method to the refresh endpoint shown above.'),
        $this->t('Include X-NCMS-Timestamp as a Unix timestamp in seconds. The timestamp must be within the configured signature time to live.'),
        $this->t('Include X-NCMS-Signature as sha256=&lt;hex digest&gt;. The digest is an HMAC-SHA256 signature of &lt;timestamp&gt;.&lt;raw request body&gt; using the configured webhook secret.'),
        $this->t('Use a new deliveryId UUID for each delivery. Duplicate delivery ids are accepted but are not queued again.'),
      ],
    ];
    $form['remote_refresh']['documentation']['payload'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Payload field'),
        $this->t('Required'),
        $this->t('Description'),
      ],
      '#rows' => [
        [
          ['data' => 'source'],
          ['data' => $this->t('Yes')],
          ['data' => $this->t('Remote source id, for example hpc_content_module.')],
        ],
        [
          ['data' => 'type'],
          ['data' => $this->t('Yes')],
          ['data' => $this->t('Remote content type. Supported values are article and document.')],
        ],
        [
          ['data' => 'id'],
          ['data' => $this->t('Yes')],
          ['data' => $this->t('Remote content id.')],
        ],
        [
          ['data' => 'deliveryId'],
          ['data' => $this->t('Yes')],
          ['data' => $this->t('Unique UUID used for replay protection.')],
        ],
        [
          ['data' => 'event'],
          ['data' => $this->t('Yes')],
          ['data' => $this->t('Supported values are saved, trashed, deleted, and ping.')],
        ],
        [
          ['data' => 'changed'],
          ['data' => $this->t('No')],
          ['data' => $this->t('Unix timestamp for the remote content change.')],
        ],
      ],
    ];
    $form['remote_refresh']['documentation']['responses'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Responses'),
      '#items' => [
        $this->t('202 with queued=true: the notification was accepted and queued.'),
        $this->t('202 with checked=true: a ping was accepted.'),
        $this->t('202 with queued=false: the delivery id was already seen.'),
        $this->t('400, 403, or 413: the payload, signature, or request size was rejected.'),
      ],
    ];
    $example_payload = json_encode([
      'source' => 'hpc_content_module',
      'type' => 'article',
      'id' => 123,
      'event' => 'saved',
      'deliveryId' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $form['remote_refresh']['documentation']['example'] = [
      '#type' => 'item',
      '#title' => $this->t('Example payload'),
      '#markup' => '<pre><code>' . Html::escape($example_payload) . '</code></pre>',
    ];
    $form['remote_refresh']['webhook_secret'] = [
      '#type' => 'password',
      '#title' => $this->t('Webhook secret'),
      '#description' => $this->t('Enter the shared secret used to validate refresh notifications from this remote source. No webhook secret is currently set.'),
      '#default_value' => $remote_refresh['webhook_secret'] ?? NULL,
    ];
    if (!empty($remote_refresh['webhook_secret'])) {
      $form['remote_refresh']['webhook_secret']['#description'] = $this->t('Enter the shared secret used to validate refresh notifications from this remote source. A webhook secret is currently set. Enter a new value to replace it, or leave this field empty to keep the current one.');
    }
    $form['remote_refresh']['signature_ttl'] = [
      '#type' => 'number',
      '#title' => $this->t('Signature time to live'),
      '#description' => $this->t('Maximum age in seconds for signed refresh notifications.'),
      '#default_value' => $remote_refresh['signature_ttl'] ?? 300,
      '#min' => 1,
      '#required' => TRUE,
    ];
    $form['remote_refresh']['max_body_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum body size'),
      '#description' => $this->t('Maximum request body size in bytes for refresh notifications.'),
      '#default_value' => $remote_refresh['max_body_size'] ?? 4096,
      '#min' => 1,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    // $configuration is the plugin configuration built from the submitted
    // form. It contains raw submitted values, not the runtime config values
    // from settings.php overrides.
    if (empty($configuration['access_key']) && !empty($this->getRemoteAccessKey())) {
      $configuration['access_key'] = $this->getRemoteAccessKey();
    }
    $stored_webhook_secret = $this->getStoredRemoteRefreshSettings()['webhook_secret'] ?? NULL;
    if (empty($configuration['remote_refresh']['webhook_secret']) && !empty($stored_webhook_secret)) {
      // The password element intentionally renders empty, so an unchanged
      // webhook secret is submitted as an empty value. Restore the stored
      // plugin configuration value before saving, otherwise the empty password
      // submission would be treated as an intentional deletion.
      $configuration['remote_refresh']['webhook_secret'] = $stored_webhook_secret;
    }
    parent::setConfiguration($configuration);
  }

  /**
   * Get a file-based config override for a remote refresh setting.
   *
   * This intentionally returns only the value supplied by Drupal's runtime
   * config system, for example from settings.php. Call
   * getRuntimeRemoteRefreshSetting() when the final usable value is needed,
   * because that method falls back to stored plugin configuration and defaults.
   *
   * @param string $key
   *   The remote refresh setting key.
   *
   * @return mixed
   *   The overridden remote refresh setting value, or NULL when no override is
   *   active for this setting.
   */
  private function getRemoteRefreshConfigOverride(string $key) {
    return $this->configFactory
      ->get('ghi_content.remote_sources')
      ->get($this->getRemoteRefreshConfigKey($key));
  }

  /**
   * Get the config key for a remote refresh setting.
   *
   * @param string $key
   *   The remote refresh setting key.
   *
   * @return string
   *   The config key.
   */
  private function getRemoteRefreshConfigKey(string $key): string {
    return $this->getPluginId() . '.remote_refresh.' . $key;
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
    $options = [];
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
    try {
      $response = $this->httpClient->get($uri, $options);
      return $response->getBody();
    }
    catch (ClientException $e) {
      // The image wasn't found or something similar.
      return NULL;
    }
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
  public function getImportIds($type, ?array $tags = NULL) {
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
      ' . $query_name . ' ' . (!empty($tags) ? '(tags:["' . implode('", "', $tags) . '"])' : '') . '{
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

  /**
   * Log an error.
   *
   * @param string|\Stringable $message
   *   The message to log.
   * @param array $context
   *   Optional: Additional context information.
   */
  private function logError(string|\Stringable $message, array $context = []): void {
    $this->loggerFactory->get(self::LOG_ID)->error($message, $context);
  }

}
