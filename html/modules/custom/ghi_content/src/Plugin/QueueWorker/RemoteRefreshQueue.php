<?php

namespace Drupal\ghi_content\Plugin\QueueWorker;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_content\ContentManager\BaseContentManager;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_content\Entity\ContentBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Refreshes local content after remote content notifications.
 */
#[QueueWorker(
  id: 'ghi_content_remote_refresh',
  title: new TranslatableMarkup('Remote content refresh'),
  cron: ['time' => 60]
)]
final class RemoteRefreshQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Delay before retrying when a matching migration is temporarily busy.
   */
  private const MIGRATION_BUSY_RETRY_DELAY = 300;

  /**
   * Maximum time to keep retrying while a matching migration is busy.
   */
  private const MIGRATION_BUSY_MAX_RETRY_AGE = 21600;

  /**
   * The content manager factory.
   *
   * @var \Drupal\ghi_content\ContentManager\ManagerFactory
   */
  protected $managerFactory;

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The targeted migration importer.
   *
   * @var \Drupal\ghi_content\Import\TargetedMigrationImporter
   */
  protected $targetedMigrationImporter;

  /**
   * The lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected CacheTagsInvalidatorInterface $cacheTagsInvalidator;

  /**
   * Logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->managerFactory = $container->get('ghi_content.manager.factory');
    $instance->migrationPluginManager = $container->get('plugin.manager.migration');
    $instance->time = $container->get('datetime.time');
    $instance->targetedMigrationImporter = $container->get('ghi_content.targeted_migration_importer');
    $instance->lock = $container->get('lock');
    $instance->cacheTagsInvalidator = $container->get('cache_tags.invalidator');
    $instance->logger = $container->get('logger.channel.ghi_content');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $source = $data->source ?? NULL;
    $type = $data->type ?? NULL;
    $remote_id = isset($data->id) ? (int) $data->id : NULL;
    $event = $data->event ?? 'unknown';
    $received = isset($data->received) ? (int) $data->received : NULL;
    if (!$source || !$type || !$remote_id) {
      return;
    }

    $lock_id = implode(':', ['ghi_content_remote_refresh', $source, $type, $remote_id]);
    // Keep updates for the same remote item serialized. A webhook can arrive
    // while cron is also importing or while another delivery for the same item
    // is still being processed.
    if (!$this->lock->acquire($lock_id, 300)) {
      $this->logger->notice('Skipped remote refresh for @type @id because another worker is processing it.', [
        '@type' => $type,
        '@id' => $remote_id,
      ]);
      return;
    }

    try {
      $remote_item_is_published = !in_array($event, ['deleted', 'trashed'], TRUE);
      // Invalidate before loading remote data so saved events clear both the
      // rendered and non-rendered persistent GraphQL variants for this item.
      $this->cacheTagsInvalidator->invalidateTags([
        $source . ':' . $type . ':' . $remote_id,
      ]);

      $node = $this->getOrImportLocalNode($source, $type, $remote_id, $event, $remote_item_is_published, $received);
      if (!$node instanceof ContentBase) {
        return;
      }

      if (!$this->applyRemoteRefresh($node, $remote_item_is_published)) {
        return;
      }

      $this->logger->info('Processed @event event for local @type node @nid from remote source @source with remote id @remote_id.', [
        '@event' => $event,
        '@type' => $type,
        '@nid' => $node->id(),
        '@source' => $source,
        '@remote_id' => $remote_id,
      ]);
    }
    finally {
      $this->lock->release($lock_id);
    }
  }

  /**
   * Load an existing node or import it when the remote item is published.
   *
   * @param string $source
   *   The remote source id.
   * @param string $type
   *   The content type.
   * @param int $remote_id
   *   The remote content id.
   * @param string $event
   *   The remote event name.
   * @param bool $remote_item_is_published
   *   Whether the remote item is currently published.
   * @param int|null $received
   *   The time when the queue item was first received.
   *
   * @return \Drupal\ghi_content\Entity\ContentBase|null
   *   The local content node if one exists or could be imported.
   */
  private function getOrImportLocalNode(string $source, string $type, int $remote_id, string $event, bool $remote_item_is_published, ?int $received): ?ContentBase {
    $node = $this->loadLocalNode($source, $type, $remote_id);
    if ($node instanceof ContentBase) {
      return $node;
    }

    if (!$remote_item_is_published) {
      // Missing deleted or trashed content should not be created locally just
      // to process an event whose outcome is "not published in HA".
      $this->logMissingNodeSkipped($source, $type, $remote_id, 'the remote item is not published');
      return NULL;
    }

    return $this->importMissingLocalNode($source, $type, $remote_id, $event, $received);
  }

  /**
   * Load the local node for a remote item.
   *
   * @param string $source
   *   The remote source id.
   * @param string $type
   *   The content type.
   * @param int $remote_id
   *   The remote content id.
   * @param bool $reload
   *   Whether to bypass a cached lookup result for this remote item.
   *
   * @return \Drupal\ghi_content\Entity\ContentBase|null
   *   The local content node if found.
   */
  private function loadLocalNode(string $source, string $type, int $remote_id, bool $reload = FALSE): ?ContentBase {
    $content_manager = $this->managerFactory->getContentManagerForRemoteType($type);
    if (!$content_manager) {
      return NULL;
    }

    // The content manager owns the bundle and remote-id field details. Keeping
    // that lookup there prevents this queue worker from knowing about
    // source-specific field names.
    $nodes = $content_manager->loadNodesForRemoteIds($source, [$remote_id], $reload);
    $node = $nodes ? reset($nodes) : NULL;
    return $node instanceof ContentBase ? $node : NULL;
  }

  /**
   * Apply the remote refresh to the local node.
   *
   * @param \Drupal\ghi_content\Entity\ContentBase $node
   *   The local content node.
   * @param bool $remote_item_is_published
   *   Whether the remote item is currently published.
   *
   * @return bool
   *   TRUE if the refresh was applied, FALSE otherwise.
   */
  private function applyRemoteRefresh(ContentBase $node, bool $remote_item_is_published): bool {
    $content_manager = $this->managerFactory->getContentManager($node);
    if (!$content_manager instanceof BaseContentManager) {
      $this->logger->warning('Remote refresh skipped local node @nid because no content manager was found.', [
        '@nid' => $node->id(),
      ]);
      return FALSE;
    }

    if (!$remote_item_is_published) {
      $node->setUnpublished();
    }
    else {
      // For saved events we refresh the already-linked local node from the
      // remote source. Deleted or trashed events intentionally take the
      // unpublished path above.
      if (!$content_manager->updateNodeFromRemote($node)) {
        $this->logger->warning('Remote refresh skipped local node @nid because remote data could not be loaded.', [
          '@nid' => $node->id(),
        ]);
        return FALSE;
      }
    }

    // Let the scheduled migration reconcile the id map; doing it here would
    // fetch the full remote export for every single webhook item.
    $content_manager->saveContentNode($node, FALSE);
    if ($remote_item_is_published && $node instanceof Article) {
      // Paragraph blocks render individual paragraphs from the rendered
      // article payload, so warm that variant while handling the webhook.
      $content_manager->loadRemoteContentForNode($node, TRUE);
    }
    return TRUE;
  }

  /**
   * Import a missing local node.
   *
   * @param string $source
   *   The remote source id.
   * @param string $type
   *   The content type.
   * @param int $remote_id
   *   The remote content id.
   * @param string $event
   *   The remote event name.
   * @param int|null $received
   *   The time when the queue item was first received.
   *
   * @return \Drupal\ghi_content\Entity\ContentBase|null
   *   The imported local content node if one could be created.
   */
  private function importMissingLocalNode(string $source, string $type, int $remote_id, string $event, ?int $received): ?ContentBase {
    $migration = $this->getImportMigration($source, $type);
    if (!$migration instanceof MigrationInterface) {
      // Without a matching migration we do not know how to create the local
      // node while preserving the normal import mappings.
      $this->logMissingNodeSkipped($source, $type, $remote_id, 'no matching import migration was found');
      return NULL;
    }

    if ($migration->getStatus() !== MigrationInterface::STATUS_IDLE) {
      if (!$this->shouldRetryBusyMigration($received)) {
        $this->logger->error('Remote refresh stopped retrying local @type @id from @source because the migration stayed busy for too long.', [
          '@type' => $type,
          '@id' => $remote_id,
          '@source' => $source,
        ]);
        return NULL;
      }

      // A busy migration is transient. Delay the queue item instead of marking
      // it processed so the webhook can still create the missing node later.
      $this->logger->notice('Delayed remote refresh creating local @type @id from @source because the migration is not idle.', [
        '@type' => $type,
        '@id' => $remote_id,
        '@source' => $source,
      ]);
      throw new DelayedRequeueException(self::MIGRATION_BUSY_RETRY_DELAY);
    }

    if (!$this->targetedMigrationImporter->import($migration, $remote_id)) {
      $this->logger->error('Remote refresh failed to import missing local @type @id from @source.', [
        '@type' => $type,
        '@id' => $remote_id,
        '@source' => $source,
      ]);
      return NULL;
    }

    // The first lookup in this request may have cached the remote id as
    // missing. Force a fresh lookup before checking whether the targeted import
    // has created the node.
    $node = $this->loadLocalNode($source, $type, $remote_id, TRUE);
    if ($node instanceof ContentBase) {
      $this->logger->info('Created local @type node @nid from @event event from remote source @source with remote id @remote_id.', [
        '@type' => $type,
        '@nid' => $node->id(),
        '@event' => $event,
        '@source' => $source,
        '@remote_id' => $remote_id,
      ]);
    }
    else {
      $this->logger->error('Remote refresh import completed but no local @type node was found for remote source @source with remote id @remote_id.', [
        '@type' => $type,
        '@source' => $source,
        '@remote_id' => $remote_id,
      ]);
    }
    return $node;
  }

  /**
   * Decide whether to keep retrying an item blocked by a busy migration.
   *
   * @param int|null $received
   *   The time when the queue item was first received.
   *
   * @return bool
   *   TRUE if the item should be delayed and retried.
   */
  private function shouldRetryBusyMigration(?int $received): bool {
    if (!$received) {
      return TRUE;
    }
    return $this->time->getRequestTime() - $received < self::MIGRATION_BUSY_MAX_RETRY_AGE;
  }

  /**
   * Get the migration for a remote refresh item.
   *
   * @param string $source
   *   The remote source id.
   * @param string $type
   *   The content type.
   *
   * @return \Drupal\migrate\Plugin\MigrationInterface|null
   *   The migration or NULL if no matching import exists.
   */
  private function getImportMigration(string $source, string $type): ?MigrationInterface {
    foreach ($this->migrationPluginManager->getDefinitions() as $migration_id => $definition) {
      $source_configuration = $definition['source'] ?? [];
      if (($source_configuration['remote_source'] ?? NULL) !== $source) {
        continue;
      }
      if (($source_configuration['content_type'] ?? NULL) !== $type) {
        continue;
      }

      // Use the migration metadata instead of hard-coding source-specific
      // migration ids here. Adding another remote source should only require a
      // matching migration definition and content manager support for the type.
      /** @var \Drupal\migrate\Plugin\MigrationInterface|null $migration */
      $migration = $this->migrationPluginManager->createInstance($migration_id);
      return $migration instanceof MigrationInterface ? $migration : NULL;
    }
    return NULL;
  }

  /**
   * Log that a remote refresh item cannot be applied without a local node.
   *
   * @param string $source
   *   The remote source id.
   * @param string $type
   *   The content type.
   * @param int $remote_id
   *   The remote content id.
   * @param string $reason
   *   The reason why the item cannot be applied.
   */
  private function logMissingNodeSkipped(string $source, string $type, int $remote_id, string $reason): void {
    $this->logger->notice('Remote refresh skipped for missing local @type @id from @source because @reason.', [
      '@type' => $type,
      '@id' => $remote_id,
      '@source' => $source,
      '@reason' => $reason,
    ]);
  }

}
