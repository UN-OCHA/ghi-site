<?php

namespace Drupal\hpc_api;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Class representing an endpoint query.
 *
 * Includes data retrieval and error handling.
 */
class ConfigService {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new Config Service object.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Get the default API version.
   *
   * @return string
   *   The default API version string.
   */
  public function getDefaultApiVersion() {
    return $this->configFactory->get('hpc_api.settings')->get('default_api_version', 'v1');
  }

  /**
   * Get a config setting by key.
   *
   * Pass through to the config object.
   *
   * @param string $key
   *   The settings key to retrieve.
   * @param mixed $default_value
   *   The default value to set.
   *
   * @return mixed
   *   The value of the config setting.
   */
  public function get($key, $default_value = NULL) {
    return $this->configFactory->get('hpc_api.settings')->get($key, $default_value);
  }

}
