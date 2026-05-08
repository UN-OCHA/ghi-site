<?php

namespace Drupal\ghi_content\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\ContentManager\DocumentManager;
use Drupal\ghi_content\Entity\ContentBase;
use Drupal\node\NodeInterface;
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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The content manager factory.
   *
   * @var \Drupal\ghi_content\ContentManager\ManagerFactory
   */
  protected $managerFactory;

  /**
   * The lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

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
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->managerFactory = $container->get('ghi_content.manager.factory');
    $instance->lock = $container->get('lock');
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
    if (!$source || !$type || !$remote_id) {
      return;
    }

    $lock_id = implode(':', ['ghi_content_remote_refresh', $source, $type, $remote_id]);
    if (!$this->lock->acquire($lock_id, 300)) {
      $this->logger->notice('Skipped remote refresh for @type @id because another worker is processing it.', [
        '@type' => $type,
        '@id' => $remote_id,
      ]);
      return;
    }

    try {
      $node = $this->loadNode($source, $type, $remote_id);
      if (!$node instanceof ContentBase) {
        $this->logger->notice('Remote refresh skipped for missing local @type @id from @source.', [
          '@type' => $type,
          '@id' => $remote_id,
          '@source' => $source,
        ]);
        return;
      }

      $content_manager = $this->managerFactory->getContentManager($node);
      if (!$content_manager) {
        return;
      }

      if (isset($data->status) && (int) $data->status === NodeInterface::NOT_PUBLISHED) {
        $node->setUnpublished();
      }
      else {
        $content_manager->updateNodeFromRemote($node);
      }
      // Let the scheduled migration reconcile the id map; doing it here would
      // fetch the full remote export for every single webhook item.
      $content_manager->saveContentNode($node, FALSE);

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
   * Load the local node for a remote item.
   *
   * @param string $source
   *   The remote source id.
   * @param string $type
   *   The content type.
   * @param int $remote_id
   *   The remote content id.
   *
   * @return \Drupal\ghi_content\Entity\ContentBase|null
   *   The local content node if found.
   */
  private function loadNode(string $source, string $type, int $remote_id): ?ContentBase {
    $map = [
      'article' => [
        'bundle' => ArticleManager::ARTICLE_BUNDLE,
        'field' => ArticleManager::REMOTE_ARTICLE_FIELD,
        'id_column' => 'article_id',
      ],
      'document' => [
        'bundle' => DocumentManager::DOCUMENT_BUNDLE,
        'field' => DocumentManager::REMOTE_DOCUMENT_FIELD,
        'id_column' => 'document_id',
      ],
    ];
    if (!isset($map[$type])) {
      return NULL;
    }

    $results = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => $map[$type]['bundle'],
      $map[$type]['field'] . '.remote_source' => $source,
      $map[$type]['field'] . '.' . $map[$type]['id_column'] => $remote_id,
    ]);
    $node = $results ? reset($results) : NULL;
    return $node instanceof ContentBase ? $node : NULL;
  }

}
