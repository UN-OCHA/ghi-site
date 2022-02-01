<?php

namespace Drupal\ghi_content\Plugin\Block;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ghi_blocks\Interfaces\AutomaticTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;

/**
 * Base class for HPC Block plugins.
 */
abstract class GhiContentBlockBase extends GHIBlockBase implements AutomaticTitleBlockInterface {

  /**
   * The remote source service.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceInterface
   */
  protected $remoteSource;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager $remote_source_manager */
    $remote_source_manager = $container->get('plugin.manager.remote_source');
    $instance->remoteSource = $remote_source_manager->createInstance($plugin_definition['remote_source']);

    return $instance;
  }

  /**
   * Get the remote source for a block.
   */
  protected function getRemoteSource() {
    return $this->remoteSource;
  }

}
