<?php

namespace Drupal\ghi_blocks\Plugin\QueueWorker;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\ghi_blocks\Interfaces\ConfigurationUpdateInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\node\NodeStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
* Queue Worker for updating plugin configuration.
*
* @QueueWorker(
*   id = "ghi_blocks_plugin_configuration_update",
*   title = @Translation("Update plugin configuration"),
*   cron = {"time" = 60}
* )
*/
final class PluginConfigurationUpdate extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Used to grab functionality from the container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   * @param array $configuration
   *   Configuration array.
   * @param mixed $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * Processes an item in the queue.
   *
   * @param mixed $data
   *   The queue item data.
   */
  public function processItem($data) {
    $entity_type_id = $data->entity_type_id;
    $entity_id = $data->entity_id;
    $plugin_id = $data->plugin_id;

    $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $entity_storage->loadUnchanged($entity_id);

    $this->processEntity($entity, $plugin_id);

    if ($entity_storage instanceof NodeStorageInterface) {
      $revision_ids = $entity_storage->revisionIds($entity);
      foreach ($revision_ids as $revision_id) {
        $revision = $entity_storage->loadRevision($revision_id);
        $this->processEntity($revision, $plugin_id);
      }
    }
  }

  /**
   * Process the given entity and update plugins by plugin id.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to update.
   * @param string $plugin_id
   *   The id of the plugin that should be updated.
   */
  private function processEntity(EntityInterface $entity, $plugin_id) {
    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }
    if (!$entity->hasField(OverridesSectionStorage::FIELD_NAME)) {
      return;
    }
    $sections = $entity->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    if (empty($sections)) {
      return;
    }
    /** @var \Drupal\layout_builder\Section $section */
    $section = &$sections[0]['section'];
    $components = $section->getComponents();
    if (empty($components)) {
      return;
    }
    $changed = FALSE;
    foreach ($components as $component) {
      if ($component->getPluginId() != $plugin_id) {
        continue;
      }
      $plugin = $component->getPlugin();
      if (!$plugin instanceof ConfigurationUpdateInterface || !$plugin instanceof ConfigurableInterface) {
        continue;
      }
      $updated = $plugin->updateConfiguration();
      if ($updated) {
        $component->setConfiguration($plugin->getConfiguration());
      }
      $changed = $changed || $updated;

    }

    if ($changed) {
      $entity->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
      if ($entity instanceof RevisionableInterface) {
        $entity->setNewRevision(FALSE);
      }
      if ($entity instanceof SynchronizableInterface) {
        $entity->setSyncing(TRUE);
      }
      $entity->save();
    }
  }

}
