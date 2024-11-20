<?php

namespace Drupal\ghi_content\RemoteSource;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\RemoteContent\RemoteParagraphInterface;
use Drupal\ghi_content\RemoteSourceLazyIterator;
use Drupal\hpc_common\Hid\HidUserData;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Base class for remote source plugins.
 */
abstract class RemoteSourceBase extends PluginBase implements RemoteSourceInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The request client service.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The HID user data service.
   *
   * @var \Drupal\hpc_common\Hid\HidUserData
   */
  protected $hidUserData;

  /**
   * The article manager.
   *
   * @var \Drupal\ghi_content\ContentManager\ArticleManager
   */
  protected $articleManager;

  /**
   * Flag to disable the cache when retrieving article data.
   *
   * @var bool
   *   TRUE if the cache should disabled, FALSE to use the cache if available.
   */
  protected $disableCache;

  /**
   * The cache base time as a timestamp.
   *
   * If set, cached data created before this time will not be used. This is
   * useful in batch contexts.
   *
   * @var int
   */
  protected $cacheBaseTime;

  /**
   * Constructs a new remote source object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientInterface $http_client, RequestStack $request, ConfigFactoryInterface $config_factory, HidUserData $hid_user_data, ArticleManager $article_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->request = $request;
    $this->configFactory = $config_factory;
    $this->hidUserData = $hid_user_data;
    $this->articleManager = $article_manager;

    // Set some flags.
    $this->disableCache = FALSE;
    $this->cacheBaseTime = NULL;

    // Init the configuration based on stored values.
    $config = $this->configFactory->get('ghi_content.remote_sources')->getOriginal($this->getPluginId());
    $this->setConfiguration($config ? $config : []);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('request_stack'),
      $container->get('config.factory'),
      $container->get('hpc_common.hid_user_data'),
      $container->get('ghi_content.manager.article'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginLabel() {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDescription() {
    $plugin_definition = $this->getPluginDefinition();
    if (empty($plugin_definition['description'])) {
      return NULL;
    }
    return $plugin_definition['description'];
  }

  /**
   * {@inheritdoc}
   */
  abstract public function buildConfigurationForm(array $form, FormStateInterface $form_state);

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function saveConfiguration() {
    $this->configFactory->getEditable('ghi_content.remote_sources')->set($this->getPluginId(), $this->configuration)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getContent($type, $id) {
    $method = 'get' . ucfirst(strtolower($type));
    if (!method_exists($this, $method)) {
      return NULL;
    }
    return $this->{$method}($id);
  }

  /**
   * {@inheritdoc}
   */
  abstract public function getImportIds($type, ?array $tags);

  /**
   * {@inheritdoc}
   */
  abstract public function getImportMetaData($type, ?array $tags);

  /**
   * {@inheritdoc}
   */
  abstract public function getImportData($type, $id);

  /**
   * {@inheritdoc}
   */
  public function getIterator($type, $tags = NULL) {
    return new RemoteSourceLazyIterator($this, $type, $tags ?? []);
  }

  /**
   * {@inheritdoc}
   */
  public function getFileContent($uri) {
    return file_get_contents($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function getLinkMap(RemoteParagraphInterface $paragraph) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function disableCache($status = TRUE) {
    $this->disableCache = $status;
  }

  /**
   * {@inheritdoc}
   */
  public function setCacheBaseTime($timestamp) {
    $this->cacheBaseTime = $timestamp;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheBaseTime() {
    return $this->cacheBaseTime;
  }

}
