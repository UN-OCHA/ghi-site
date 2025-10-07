<?php

namespace Drupal\ghi_blocks\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller class for pages with invalid layout definitions.
 */
class InvalidLayoutReportController extends ControllerBase {

  use LayoutEntityHelperTrait;

  /**
   * The name of the queue for merging layout sections.
   */
  const QUEUE_NAME = 'ghi_blocks_merge_layout_sections';

  /**
   * The current request.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current request.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The section manager manager.
   *
   * @var \Drupal\ghi_blocks\LayoutBuilder\LayoutSectionManager
   */
  protected $layoutSectionManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = new static();
    $instance->database = $container->get('database');
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->queue = $container->get('queue')->get(self::QUEUE_NAME);
    $instance->layoutSectionManager = $container->get('ghi_blocks.layout_section_manager');
    return $instance;
  }

  /**
   * Build a page listing nodes with invalid layout definitions.
   *
   * @return array
   *   A render array with a table and some explanations.
   */
  public function list(): array {
    $header = [
      $this->t('Page title'),
      $this->t('Updated'),
      $this->t('# Layout sections'),
      $this->t('Queued for fixing'),
      $this->t('Status'),
      $this->t('Operations'),
    ];
    $rows = [];
    foreach ($this->getNodes() as $node) {
      $section_storage = $this->getSectionStorageForEntity($node);
      $queue_item_exists = $this->queueItemExists($node);

      // Prepare the operation links.
      $operations = [];
      if (!$queue_item_exists) {
        $operations['merge_sections'] = [
          'title' => $this->t('Merge sections'),
          'weight' => -1,
          'url' => Url::fromRoute('ghi_blocks.node.merging_sections', ['node' => $node->id()]),
        ];
      }

      $rows[] = [
        $node->toLink(),
        $this->dateFormatter->format($node->getChangedTime()),
        count($section_storage->getSections()),
        $queue_item_exists ? $this->t('Yes') : $this->t('No'),
        [
          'data' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $node->isPublished() ? $this->t('Displayed') : $this->t('Not displayed'),
            '#attributes' => [
              'class' => array_filter([
                'gin-status',
                $node->isPublished() ? 'gin-status--success' : NULL,
              ]),
            ],
          ],
        ],
        !empty($operations) ? [
          'data' => [
            '#type' => 'operations',
            '#links' => $operations,
          ],
        ] : NULL,
      ];
    }

    $table = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('Nothing here yet'),
    ];

    $header = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['gin-layer-wrapper'],
      ],
      [
        '#markup' => $this->t('This table lists pages that have an invalid layout definition. <br />See below under <em>Technical background</em> for an explanation of the problem.'),
      ],
      [
        '#type' => 'details',
        '#title' => $this->t('Technical background'),
        '#open' => FALSE,
        'hints' => [
          '#theme' => 'item_list',
          '#attributes' => [
            'class' => ['claro-details__wrapper'],
          ],
          '#items' => [
            'This website uses the Drupal layout builder module for page layouts on all customizable pages. This modules works by creating sections with layouts for every page, where each section can hold different page elements.',
            'For consistency and ease of use and presentation, we assume a single column layout with a single layout builder section on all pages that can be customized via the frontend interface.',
            'If a page\'s layout definition contains multiple sections, some base functionality of this website does not work properly, e.g. page config export/import, the section navigation providing the section menus, page templates.',
          ],
        ],
      ],
    ];

    $build = [
      $header,
      $table,
    ];
    return $build;
  }

  /**
   * Queue nodes for merging of their sections.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to the reports page.
   */
  public function queueForMerging(): RedirectResponse {
    $nodes = $this->getNodes();
    $count = 0;
    foreach ($nodes as $node) {
      if ($this->queueItemExists($node)) {
        continue;
      }
      $this->queue->createItem($this->buildQueueItem($node));
      $count++;
    }
    $this->messenger()->addMessage($this->t('Queued @count pages for merging of layout sections.', [
      '@count' => $count,
    ]));
    return $this->redirect('ghi_blocks.reports.invalid_layout_definitions');
  }

  /**
   * Callback for merging of node layout sections.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to the reports page.
   */
  public function mergeSections(NodeInterface $node) {
    $this->layoutSectionManager->mergeSections($node);
    $affected_nodes = $this->getNodes();
    $t_args = [
      '@label' => $node->label(),
    ];
    if (array_key_exists($node->id(), $affected_nodes)) {
      $this->messenger()->addError($this->t('Failed to merge layout sections for <em>@label</em>.', $t_args));
    }
    else {
      $this->messenger()->addMessage($this->t('Layout sections for <em>@label</em> have been merged.', $t_args));
    }
    return $this->redirect('ghi_blocks.reports.invalid_layout_definitions');
  }

  /**
   * Build a queue item for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return object
   *   An object representing the queue item.
   */
  private function buildQueueItem(NodeInterface $node) {
    return (object) [
      'entity_id' => $node->id(),
      'entity_type_id' => $node->getEntityTypeId(),
    ];
  }

  /**
   * Check if a queue item for the given node already exists.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return bool
   *   TRUE if a queue item already exists, FALSE otherwise.
   */
  private function queueItemExists(NodeInterface $node): bool {
    $item = $this->buildQueueItem($node);
    $query = $this->database->select('queue', 'q')
      ->condition('name', self::QUEUE_NAME)
      ->condition('data', serialize($item))
      ->condition('expire', 0)
      ->fields('q', ['item_id']);
    return !empty($query->execute()->fetchAll());
  }

  /**
   * Get nodes with invalid layout defintions.
   *
   * @return \Drupal\node\NodeInterface[]
   *   An array of node objects.
   */
  private function getNodes(): array {
    $result = $this->database->select('node__layout_builder__layout')
      ->fields('node__layout_builder__layout', ['entity_id'])
      ->condition('delta', 0, '>')
      ->orderBy('entity_id', 'DESC')
      ->execute();

    $entity_ids = array_map(function ($row) {
      return $row->entity_id;
    }, $result->fetchAll());

    /** @var \Drupal\node\NodeInterface[] $nodes */
    return $this->entityTypeManager()->getStorage('node')->loadMultiple($entity_ids);
  }

}
