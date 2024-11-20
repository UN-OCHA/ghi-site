<?php

namespace Drupal\ghi_content\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Event\ImportAwareInterface;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
class RemoteSourceGraphQL extends SourcePluginBase implements ContainerFactoryPluginInterface, ImportAwareInterface {

  /**
   * The remote source for this migration.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceInterface
   */
  private $remoteSource;

  /**
   * The private tempstore.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  private $store;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    $instance = new static($configuration, $plugin_id, $plugin_definition, $migration);
    /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager $remote_source_manager */
    $remote_source_manager = $container->get('plugin.manager.remote_source');
    $instance->remoteSource = $remote_source_manager->createInstance($configuration['remote_source']);
    $instance->store = $container->get('tempstore.private')->get($migration->id());
    $instance->time = $container->get('datetime.time');
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
    $type = $this->migration->getSourceConfiguration()['content_type'] ?? NULL;
    if (!$type) {
      return [];
    }
    $this->remoteSource->setCacheBaseTime($this->getCacheBaseTime());
    $tags = $this->getSourceTags();
    $results = $this->remoteSource->getImportMetaData($type, $tags);
    $object = new \ArrayObject($results);
    return $object->getIterator();
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

  /**
   * Forwarded pre-import event.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event.
   */
  public function preImport(MigrateImportEvent $event) {
    $this->setCacheBaseTime($this->time->getRequestTime());
  }

  /**
   * Forwarded post-import event.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event.
   */
  public function postImport(MigrateImportEvent $event) {}

  /**
   * Cleanup our private storage for the current migration.
   */
  public function cleanup() {
    $this->store->delete('source_tags');
    $this->store->delete('cache_base_time');
  }

  /**
   * Set the tags used to limit the source data.
   *
   * @param array $tags
   *   An array of tag names keyed by tag id.
   */
  public function setSourceTags(array $tags) {
    $this->store->set('source_tags', $tags);
  }

  /**
   * Get the tags used to limit the source data.
   *
   * @return array
   *   An array of tag names keyed by tag id.
   */
  public function getSourceTags() {
    return $this->store->get('source_tags');
  }

  /**
   * Set the tags used to limit the source data.
   *
   * @param int $cache_base_time
   *   A timestamp to use as the base time for cacheing.
   */
  public function setCacheBaseTime($cache_base_time) {
    $this->store->set('cache_base_time', $cache_base_time);
  }

  /**
   * Set the tags used to limit the source data.
   *
   * @return int
   *   A timestamp to use as the base time for cacheing.
   */
  public function getCacheBaseTime() {
    return $this->store->get('cache_base_time');
  }

}
