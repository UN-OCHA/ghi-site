<?php

namespace Drupal\ghi_blocks\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
* Update monitoring period queue Worker.
*
* @QueueWorker(
*   id = "ghi_blocks_update_monitoring_period_queue",
*   title = @Translation("Update monitoring period queue"),
*   cron = {"time" = 60}
* )
*/
final class UpdateMonitoringPeriodQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use LayoutEntityHelperTrait;

  /**
   * The plugin ids that this queue worker should update.
   */
  const PLUGIN_IDS = [
    'plan_headline_figures',
    'plan_entity_attachments_table',
    'plan_governing_entities_caseloads_table',
  ];

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
    $changed = FALSE;
    $components = $section_storage->getSection(0)->getComponents();
    foreach ($components as $component) {
      if (!in_array($component->getPluginId(), self::PLUGIN_IDS)) {
        continue;
      }
      $configuration = $component->toArray()['configuration'];
      switch ($component->getPluginId()) {
        case 'plan_headline_figures':
          if (!empty($configuration['hpc']['key_figures']['items'] ?? [])) {
            $this->updateConfigurationItems($configuration['hpc']['key_figures']['items'], $changed);
          }
          break;

        case 'plan_entity_attachments_table':
        case 'plan_governing_entities_caseloads_table':
          if (!empty($configuration['hpc']['table']['columns'] ?? [])) {
            $this->updateConfigurationItems($configuration['hpc']['table']['columns'], $changed);
          }
          break;
      }
      $component->setConfiguration($configuration);
    }

    if ($changed) {
      $section_storage->save();
      $this->layoutTempstoreRepository->delete($section_storage);
    }
  }

  /**
   * Update the given configuration items.
   *
   * @param array $items
   *   An array of items.
   * @param bool $changed
   *   Flag to indicate if a change has been made.
   */
  private function updateConfigurationItems(array &$items, &$changed) {
    $changed = FALSE;
    foreach ($items as &$item) {
      if (!in_array($item['item_type'], ['attachment_data', 'data_point']) || empty($item['config']['data_point'])) {
        continue;
      }
      $item['config']['data_point']['data_points'] = array_map(function ($data_point) use (&$changed) {
        if (!array_key_exists('monitoring_period', $data_point) || $data_point['monitoring_period'] != 'latest') {
          $changed = TRUE;
        }
        $data_point['monitoring_period'] = 'latest';
        return $data_point;
      }, $item['config']['data_point']['data_points']);
    }

  }

}
