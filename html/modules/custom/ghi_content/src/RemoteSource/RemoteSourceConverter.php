<?php

namespace Drupal\ghi_content\RemoteSource;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Symfony\Component\Routing\Route;

/**
 * Converts parameters for upcasting remote source ids to full objects.
 */
class RemoteSourceConverter implements ParamConverterInterface {

  /**
   * The remote source manager.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager
   */
  protected $remoteSourceManager;

  /**
   * Constructs a new LanguageConverter.
   *
   * @param \Drupal\ghi_content\RemoteSource\RemoteSourceManager $remote_source_manager
   *   The language manager.
   */
  public function __construct(RemoteSourceManager $remote_source_manager) {
    $this->remoteSourceManager = $remote_source_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (!empty($value)) {
      return $this->remoteSourceManager->createInstance($value);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return $name == 'remote_source' || (!empty($definition['type']) && $definition['type'] == 'remote_source');
  }

}
