<?php

namespace Drupal\ghi_blocks\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Drupal\layout_builder_lock\LayoutBuilderLock;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
* Section lock update queue Worker.
*
* @QueueWorker(
*   id = "ghi_blocks_section_lock_update_queue",
*   title = @Translation("Section lock update queue"),
*   cron = {"time" = 60}
* )
*/
final class SectionLockUpdateQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use LayoutEntityHelperTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

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
    $instance->layoutTempstoreRepository = $container->get('layout_builder.tempstore_repository');
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
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->loadUnchanged($entity_id);

    $section_storage = $this->getSectionStorageForEntity($entity);
    if (!$section_storage || !$section_storage instanceof OverridesSectionStorageInterface) {
      return;
    }

    /** @var \Drupal\layout_builder\Section $section */
    $section_storage->getSection(0)->setThirdPartySetting('layout_builder_lock', 'lock', [
      LayoutBuilderLock::LOCKED_SECTION_CONFIGURE => LayoutBuilderLock::LOCKED_SECTION_CONFIGURE,
      LayoutBuilderLock::LOCKED_SECTION_BEFORE => LayoutBuilderLock::LOCKED_SECTION_BEFORE,
      LayoutBuilderLock::LOCKED_SECTION_AFTER => LayoutBuilderLock::LOCKED_SECTION_AFTER,
    ]);
    $section_storage->save();
    $this->layoutTempstoreRepository->delete($section_storage);
  }

}
