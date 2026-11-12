<?php

namespace Drupal\ghi_blocks\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_blocks\Services\PlanHeadlineFiguresConfigMigrator;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Migrates active plan headline figure configurations.
 */
#[QueueWorker(
  id: 'ghi_blocks_migrate_plan_headline_figures',
  title: new TranslatableMarkup('Migrate plan headline figures'),
  cron: ['time' => 60]
)]
final class MigratePlanHeadlineFigures extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Public constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, private EntityTypeManagerInterface $entityTypeManager, private PlanHeadlineFiguresConfigMigrator $configMigrator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'), $container->get('ghi_blocks.plan_headline_figures_config_migrator'));
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $storage = $this->entityTypeManager->getStorage($data->entity_type_id);
    try {
      $entity = $storage->loadUnchanged($data->entity_id);
      if (!$entity instanceof FieldableEntityInterface || !$entity->hasField(OverridesSectionStorage::FIELD_NAME)) {
        return;
      }

      $plan = $this->getPlanContext($entity);
      if (!$plan) {
        // Headline figures also appear on cluster pages. Only configurations
        // whose primary data context is the plan itself are eligible.
        return;
      }

      $sections = $entity->get(OverridesSectionStorage::FIELD_NAME)->getValue();
      $changed = FALSE;
      foreach ($sections as $section_item) {
        if (empty($section_item['section'])) {
          continue;
        }
        foreach ($section_item['section']->getComponents() as $component) {
          if ($component->getPluginId() !== 'plan_headline_figures') {
            continue;
          }
          $configuration = $component->get('configuration');
          if ($this->configMigrator->migrateConfiguration($configuration, $plan)) {
            $component->setConfiguration($configuration);
            $changed = TRUE;
          }
        }
      }
      if (!$changed) {
        return;
      }

      $entity->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
      if ($entity instanceof RevisionableInterface && $entity->getEntityType()->hasKey('revision')) {
        // Active configurations are updated in place without creating a
        // content revision solely for this mechanical migration.
        $entity->setNewRevision(FALSE);
      }
      if ($entity instanceof SynchronizableInterface) {
        $entity->setSyncing(TRUE);
      }
      $entity->save();
    }
    finally {
      // Cron queue processing can otherwise retain many loaded layout graphs.
      $storage->resetCache([$data->entity_id]);
      gc_collect_cycles();
    }
  }

  /**
   * Gets the primary plan data context for an entity or page template.
   */
  private function getPlanContext(FieldableEntityInterface $entity): ?Plan {
    $context_entity = $entity->getEntityTypeId() === 'page_template' && method_exists($entity, 'getSourceEntity') ? $entity->getSourceEntity() : $entity;
    if (!$context_entity instanceof FieldableEntityInterface) {
      return NULL;
    }
    $base_object = BaseObjectHelper::getBaseObjectFromNode($context_entity);
    return $base_object instanceof Plan ? $base_object : NULL;
  }

}
