<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Content block plugins.
 */
abstract class ContentBlockBase extends GHIBlockBase {

  /**
   * The remote source service.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceInterface
   */
  protected $remoteSource;

  /**
   * The article manager.
   *
   * @var \Drupal\ghi_content\ContentManager\ArticleManager
   */
  protected $articleManager;

  /**
   * The document manager.
   *
   * @var \Drupal\ghi_content\ContentManager\DocumentManager
   */
  protected $documentManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    if (!empty($plugin_definition['remote_source'])) {
      /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager $remote_source_manager */
      $remote_source_manager = $container->get('plugin.manager.remote_source');
      $instance->remoteSource = $remote_source_manager->createInstance($plugin_definition['remote_source']);
    }
    $instance->articleManager = $container->get('ghi_content.manager.article');
    $instance->documentManager = $container->get('ghi_content.manager.document');
    $instance->renderer = $container->get('renderer');

    return $instance;
  }

  /**
   * Get the remote source for a block.
   */
  protected function getRemoteSource() {
    return $this->remoteSource;
  }

}
