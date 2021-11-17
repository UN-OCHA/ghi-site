<?php

namespace Drupal\ghi_content\RemoteSource;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use GuzzleHttp\ClientInterface;

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
   * Constructs a new remote source object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientInterface $http_client, RequestStack $request, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->request = $request;
    $this->configFactory = $config_factory;

    // Init the configuration based on stored values.
    $config = $this->configFactory->get('ghi_content.remote_sources')->get($this->getPluginId());
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

}
