<?php

namespace Drupal\ghi_blocks\Plugin\QueueWorker;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\ghi_blocks\Interfaces\DeprecatedBlockInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\NodeStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
* Replace deprecated blocks queue Worker.
*
* @QueueWorker(
*   id = "ghi_blocks_replace_deprecated_blocks_queue",
*   title = @Translation("Replace deprecated blocks queue"),
*   cron = {"time" = 60}
* )
*/
final class ReplaceDeprecatedBlocksQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use LayoutEntityHelperTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The block plugin manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockPluginManager;

  /**
   * The uuid service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

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
    $instance->blockPluginManager = $container->get('plugin.manager.block');
    $instance->uuid = $container->get('uuid');
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
    $this->replacePluginInEntity($entity, $plugin_id);

    if ($entity_storage instanceof NodeStorageInterface) {
      $revision_ids = $entity_storage->revisionIds($entity);
      foreach ($revision_ids as $revision_id) {
        $revision = $entity_storage->loadRevision($revision_id);
        $this->replacePluginInEntity($revision, $plugin_id);
      }
    }
  }

  /**
   * Replace specific plugins in the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to process.
   * @param string $plugin_id
   *   The plugin id of the plugin to replace.
   */
  private function replacePluginInEntity(ContentEntityInterface $entity, $plugin_id) {
    if (!$entity || !$entity->hasField(OverridesSectionStorage::FIELD_NAME)) {
      return;
    }
    $sections = $entity->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    if (empty($sections)) {
      return;
    }

    foreach (array_keys($sections) as $delta) {
      /** @var \Drupal\layout_builder\Section $section */
      $components = $sections[$delta]['section']->getComponents();
      /** @var \Drupal\layout_builder\SectionComponent[] $components */
      foreach ($components as $component) {
        if ($component->getPluginId() != $plugin_id) {
          continue;
        }
        $plugin = $component->getPlugin();
        if (!$plugin instanceof DeprecatedBlockInterface) {
          continue;
        }
        $config = $plugin->getBlockConfigForReplacement();
        if (!$config) {
          continue;
        }

        // Create the new component.
        $entity_logframe_component = new SectionComponent($this->uuid->generate(), $component->getRegion(), $config);
        $entity_logframe_component->setWeight($component->getWeight());

        // And put it into the same position as the component it replaces.
        $_section = $sections[$delta]['section']->toArray();
        array_splice($_section['components'], array_search($component->getUuid(), array_keys($_section['components'])), 1, [$entity_logframe_component->getUuid() => $entity_logframe_component->toArray()]);
        $sections[$delta]['section'] = Section::fromArray($_section);
      }
    }

    $entity->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
    $entity->setNewRevision(FALSE);
    $entity->setSyncing(TRUE);
    $entity->save();
  }

}
