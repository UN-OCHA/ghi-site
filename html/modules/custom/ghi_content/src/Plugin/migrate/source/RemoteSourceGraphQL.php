<?php

namespace Drupal\ghi_content\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Retrieve data via a RemoteSourceGraphQL interface.
 *
 * Example:
 *
 * @code
 * source:
 *   plugin: remote_source_graphql
 *
 *   headers:
 *     Accept: application/json
 *     User-Agent: Internet Explorer 6
 *     Authorization-Key: secret
 *     Arbitrary-Header: foobarbaz
 * @endcode
 *
 * @MigrateSource(
 *   id = "remote_source_graphql",
 *   title = @Translation("Remote Source via GraphQL")
 * )
 */
class RemoteSourceGraphQL extends SourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * The remote source for this migration.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceInterface
   */
  private $remoteSource;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    $instance = new static($configuration, $plugin_id, $plugin_definition, $migration);
    /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager $remote_source_manager */
    $remote_source_manager = $container->get('plugin.manager.remote_source');
    $instance->remoteSource = $remote_source_manager->createInstance($configuration['remote_source']);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return (string) $this->remoteSource->getPluginLabel();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function initializeIterator() {
    return $this->getGenerator();
  }

  /**
   * Return the generator using yield.
   */
  private function getGenerator() {
    $results = $this->remoteSource->importSource();
    foreach ($results as $result) {
      yield $result;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $configuration = $this->migration->getSourceConfiguration();
    return $configuration['ids'];
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $configuration = $this->migration->getSourceConfiguration();
    $fields = [];
    foreach ($configuration['fields'] as $field) {
      $fields[$field['name']] = $field['label'];
    }
    return $fields;
  }

}
