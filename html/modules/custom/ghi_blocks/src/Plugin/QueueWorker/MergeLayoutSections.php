<?php

namespace Drupal\ghi_blocks\Plugin\QueueWorker;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
* Queue Worker for updating plugin configuration.
*
* @QueueWorker(
*   id = "ghi_blocks_merge_layout_sections",
*   title = @Translation("Merge layout sections"),
*   cron = {"time" = 60}
* )
*/
final class MergeLayoutSections extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The section manager manager.
   *
   * @var \Drupal\ghi_blocks\LayoutBuilder\LayoutSectionManager
   */
  protected $layoutSectionManager;

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
    $instance->layoutSectionManager = $container->get('ghi_blocks.layout_section_manager');
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
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->loadUnchanged($entity_id);
    $this->processEntity($entity);
  }

  /**
   * Process the given entity and update plugins by plugin id.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $node
   *   The entity to update.
   */
  private function processEntity(ContentEntityInterface $node) {
    $this->layoutSectionManager->mergeSections($node);
  }

}
