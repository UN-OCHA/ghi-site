<?php

namespace Drupal\ghi_blocks\Plugin\QueueWorker;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_blocks\Interfaces\ConfigurationUpdateInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\node\NodeStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Updates saved Layout Builder block plugin configuration.
 */
#[QueueWorker(
  id: 'ghi_blocks_plugin_configuration_update',
  title: new TranslatableMarkup('Update plugin configuration'),
  cron: ['time' => 60]
)]
final class PluginConfigurationUpdate extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Public constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, private EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $entity_storage = $this->entityTypeManager->getStorage($data->entity_type_id);
    $entity = $entity_storage->loadUnchanged($data->entity_id);
    if (!$entity) {
      return;
    }

    $this->processEntity($entity, $data->plugin_id);

    if ($entity_storage instanceof NodeStorageInterface) {
      foreach ($entity_storage->revisionIds($entity) as $revision_id) {
        $revision = $entity_storage->loadRevision($revision_id);
        if ($revision) {
          $this->processEntity($revision, $data->plugin_id);
        }
      }
    }

    // Long-running deploy batches can otherwise retain thousands of loaded
    // entity objects and their Layout Builder component graphs.
    $entity_storage->resetCache([$data->entity_id]);
    gc_collect_cycles();
  }

  /**
   * Updates matching block plugins on an entity revision.
   */
  private function processEntity(EntityInterface $entity, string $plugin_id): void {
    if (!$entity instanceof FieldableEntityInterface || !$entity->hasField(OverridesSectionStorage::FIELD_NAME)) {
      return;
    }
    $sections = $entity->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    $changed = FALSE;

    foreach ($sections as $section_item) {
      if (empty($section_item['section'])) {
        continue;
      }
      foreach ($section_item['section']->getComponents() as $component) {
        if ($component->getPluginId() !== $plugin_id) {
          continue;
        }
        $plugin = $component->getPlugin();
        if (!$plugin instanceof ConfigurationUpdateInterface || !$plugin instanceof ConfigurableInterface || !$plugin->updateConfiguration()) {
          continue;
        }
        $component->setConfiguration($plugin->getConfiguration());
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
