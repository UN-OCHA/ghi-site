<?php

namespace Drupal\ghi_blocks\Plugin\QueueWorker;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_blocks\Interfaces\DeprecatedBlockReplacementInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\NodeStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Replaces deprecated Layout Builder block plugins.
 */
#[QueueWorker(
  id: 'ghi_blocks_replace_deprecated_blocks_queue',
  title: new TranslatableMarkup('Replace deprecated blocks'),
  cron: ['time' => 60]
)]
final class ReplaceDeprecatedBlocksQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Public constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, private EntityTypeManagerInterface $entityTypeManager, private UuidInterface $uuid) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'), $container->get('uuid'));
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $entity_storage = $this->entityTypeManager->getStorage($data->entity_type_id);
    $entity = $entity_storage->loadUnchanged($data->entity_id);
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }

    $this->replacePluginInEntity($entity, $data->plugin_id);

    if ($entity_storage instanceof NodeStorageInterface) {
      foreach ($entity_storage->revisionIds($entity) as $revision_id) {
        $revision = $entity_storage->loadRevision($revision_id);
        if ($revision instanceof ContentEntityInterface) {
          $this->replacePluginInEntity($revision, $data->plugin_id);
        }
      }
    }
  }

  /**
   * Replaces matching block plugins on an entity revision.
   */
  private function replacePluginInEntity(ContentEntityInterface $entity, string $plugin_id): void {
    if (!$entity->hasField(OverridesSectionStorage::FIELD_NAME)) {
      return;
    }
    $sections = $entity->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    $changed = FALSE;

    foreach (array_keys($sections) as $delta) {
      if (empty($sections[$delta]['section'])) {
        continue;
      }
      foreach ($sections[$delta]['section']->getComponents() as $component) {
        if ($component->getPluginId() !== $plugin_id) {
          continue;
        }
        $plugin = $component->getPlugin();
        if (!$plugin instanceof DeprecatedBlockReplacementInterface) {
          continue;
        }
        $replacement_config = $plugin->getBlockConfigForReplacement();
        if (!$replacement_config) {
          continue;
        }

        $replacement = new SectionComponent($this->uuid->generate(), $component->getRegion(), $replacement_config);
        $replacement->setWeight($component->getWeight());
        $section_config = $sections[$delta]['section']->toArray();
        $position = array_search($component->getUuid(), array_keys($section_config['components']), TRUE);
        if ($position === FALSE) {
          continue;
        }
        array_splice($section_config['components'], $position, 1, [$replacement->getUuid() => $replacement->toArray()]);
        $sections[$delta]['section'] = Section::fromArray($section_config);
        $changed = TRUE;
      }
    }

    if (!$changed) {
      return;
    }
    $entity->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
    if ($entity instanceof RevisionableInterface && $entity->getEntityType()->hasKey('revision')) {
      $entity->setNewRevision(FALSE);
    }
    if ($entity instanceof SynchronizableInterface) {
      $entity->setSyncing(TRUE);
    }
    $entity->save();
  }

}
