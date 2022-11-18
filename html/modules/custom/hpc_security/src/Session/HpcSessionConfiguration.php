<?php

namespace Drupal\hpc_security\Session;

use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Session\SessionConfigurationInterface;

/**
 * An HPC specific session configuration decorator.
 *
 * It allows to set the cookie_samesite flag to "Lax" in Drupal 8.
 */
class HpcSessionConfiguration implements SessionConfigurationInterface {

  /**
   * Original service object.
   *
   * @var \Drupal\core\Session\SessionConfigurationInterface
   */
  protected $sessionConfiguration;

  /**
   * Constructs a new Session Configuration instance.
   *
   * @param \Drupal\core\Session\SessionConfigurationInterface $session_configuration
   *   The original session configuration service.
   */
  public function __construct(SessionConfigurationInterface $session_configuration) {
    $this->sessionConfiguration = $session_configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function hasSession(Request $request) {
    return $this->sessionConfiguration->hasSession($request);
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(Request $request) {
    $options = $this->sessionConfiguration->getOptions($request);
    $options['cookie_samesite'] = 'Lax';
    return $options;
  }

}
