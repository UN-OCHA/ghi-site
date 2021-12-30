<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\KeyValueStore\KeyValueFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Routing\Router;
use Drupal\ghi_blocks\Interfaces\AutomaticTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;
use Drupal\hpc_api\Query\EndpointQuery;

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
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $request_stack, Router $router, KeyValueFactory $keyValueFactory, EndpointQuery $endpoint_query, EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system, ModuleHandlerInterface $module_handler, RemoteSourceInterface $remote_source) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $request_stack, $router, $keyValueFactory, $endpoint_query, $entity_type_manager, $file_system);

    $this->moduleHandler = $module_handler;
    $this->remoteSource = $remote_source;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager $remote_source_manager */
    $remote_source_manager = $container->get('plugin.manager.remote_source');
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('router.no_access_checks'),
      $container->get('keyvalue'),
      $container->get('hpc_api.endpoint_query'),
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('module_handler'),
      $remote_source_manager->createInstance($plugin_definition['remote_source']),
    );
  }

  /**
   * Get the remote source for a block.
   */
  protected function getRemoteSource() {
    return $this->remoteSource;
  }

}
