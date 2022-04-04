<?php

namespace Drupal\ghi_content\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides an event subscriber that acts after a migration run.
 *
 * @package Drupal\ghi_documents
 */
class MigrationPostImportSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The article manager.
   *
   * @var \Drupal\ghi_content\ContentManager\ArticleManager
   */
  protected $articleManager;

  /**
   * Constructs a document manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ArticleManager $article_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->articleManager = $article_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[MigrateEvents::POST_IMPORT][] = ['unpublishMissingNodes'];
    return $events;
  }

  /**
   * Unpublish nodes from a remote graphql source that are no longer available.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The migration import event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function unpublishMissingNodes(MigrateImportEvent $event) {
    $migration = $event->getMigration();
    if ($migration->getSourcePlugin()->getPluginId() != 'remote_source_graphql') {
      return;
    }

    $id_map = $migration->getIdMap();
    $id_map->prepareUpdate();

    // Clone so that any generators aren't initialized prematurely.
    /** @var \Drupal\ghi_content\Plugin\migrate\source\RemoteSourceGraphQL $source */
    $source = clone $migration->getSourcePlugin();
    $source->rewind();
    $source_id_values = [];

    while ($source->valid()) {
      $source_id_values[] = $source->current()->getSourceIdValues();
      $source->next();
    }

    $source_tags = $source->getSourceTags();
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple(array_keys($source_tags));
    $existing_tagged_nodes = !empty($source_tags) ? $this->articleManager->loadNodesForTags($terms, NULL, 'AND', NULL, FALSE) : NULL;

    $id_map->rewind();
    while ($id_map->valid()) {
      $map_source_id = $id_map->currentSource();
      $destination_ids = $id_map->currentDestination();
      if (empty($destination_ids['nid'])) {
        $id_map->next();
        continue;
      }
      if ($existing_tagged_nodes && !array_key_exists($destination_ids['nid'], $existing_tagged_nodes)) {
        // If we have a restricted set of existing tagged nodes that we have
        // processed, skip if the current destination nid is not part of that.
        $id_map->next();
        continue;
      }

      $node = $this->entityTypeManager->getStorage('node')->load($destination_ids['nid']);
      if (!$node instanceof NodeInterface) {
        $id_map->next();
        continue;
      }

      if (!in_array($map_source_id, $source_id_values) && $node->isPublished()) {
        // This is a node that is currently published, but not part of the
        // source ids anymore.
        $node->setUnpublished();
        $node->save();
      }
      $id_map->next();
    }

  }

}
