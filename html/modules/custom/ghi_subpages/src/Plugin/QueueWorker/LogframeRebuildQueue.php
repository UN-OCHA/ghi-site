<?php

namespace Drupal\ghi_subpages\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\ghi_subpages\Entity\LogframeSubpage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
* Update monitoring period queue Worker.
*
* @QueueWorker(
*   id = "ghi_subpages_logframe_rebuild_queue",
*   title = @Translation("Logframe rebuild queue"),
*   cron = {"time" = 60}
* )
*/
final class LogframeRebuildQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
    if ($entity instanceof LogframeSubpage) {
      $entity->createPageElements();
    }
  }

}
