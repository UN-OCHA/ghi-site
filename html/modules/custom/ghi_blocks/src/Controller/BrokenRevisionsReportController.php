<?php

namespace Drupal\ghi_blocks\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ghi_content\Entity\ContentBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for pages with broken revisions.
 */
class BrokenRevisionsReportController extends ControllerBase {

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->database = $container->get('database');
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

  /**
   * Build a page listing nodes with a broken revision state.
   *
   * @return array
   *   A render array with a table and some explanations.
   */
  public function listBrokenRevisions() {
    $subselect = $this->database->select('node_revision');
    $subselect->addField('node_revision', 'nid');
    $subselect->addExpression('max(vid)', 'vid');
    $subselect->groupBy('nid');

    $select = $this->database->select($subselect, 'nr');
    $select->distinct();
    $select->addField('nr', 'nid');
    $select->leftJoin('node_field_data', 'nfd', 'nfd.nid = nr.nid');
    $select->where('[nr].[vid] != [nfd].[vid]');
    $result = $select->execute();
    $nids = array_keys($result->fetchAllAssoc('nid'));
    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $this->entityTypeManager()->getStorage('node')->loadMultiple($nids);
    foreach ($nodes as $node) {
      $orphaned = $node instanceof ContentBase && $node->isOrphaned() ?: FALSE;
      if ($orphaned) {
        continue;
      }

      /** @var \Drupal\Core\Entity\EntityListBuilderInterface $list_builder */
      $list_builder = $this->entityTypeManager()->getListBuilder($node->getEntityTypeId());

      // Prepare the operation links.
      $operations = [];
      if (!$orphaned) {
        // If the entity is not orphaned, add a link to the revision tab.
        $operations['version_history'] = [
          'title' => $this->t('Revisions'),
          'weight' => -1,
          'url' => $node->toUrl('version-history'),
        ];
      }
      // Then add the default operations, but unset what we don't need.
      $allowed_operations = [
        'edit',
      ];
      $operations += array_intersect_key($list_builder->getOperations($node), array_combine($allowed_operations, $allowed_operations));

      $rows[] = [
        $node->toLink(),
        $this->dateFormatter->format($node->getChangedTime()),
        [
          'data' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $orphaned ? $this->t('Orphaned') : ($node->isPublished() ? $this->t('Displayed') : $this->t('Not displayed')),
            '#attributes' => [
              'class' => array_filter([
                'gin-status',
                $node->isPublished() ? 'gin-status--success' : NULL,
              ]),
            ],
          ],
        ],
        [
          'data' => [
            '#type' => 'operations',
            '#links' => $operations,
          ],
        ],
      ];
    }

    $table = [
      '#theme' => 'table',
      '#header' => [
        $this->t('Page title'),
        $this->t('Updated'),
        $this->t('Status'),
        $this->t('Operations'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('Nothing here yet'),
    ];

    $header = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['gin-layer-wrapper'],
      ],
      [
        '#markup' => $this->t('This table lists pages where the revisions are broken in the way that the current revision is not the default revision. Click on the revisions link to see the current state of the revisions.<br />See <em>Manual instructions for fixing broken revisions</em> below for how to apply a fix manually.'),
      ],
      [
        '#type' => 'details',
        '#title' => $this->t('Manual instructions for fixing broken revisions'),
        '#open' => FALSE,
        'hints' => [
          '#theme' => 'item_list',
          '#attributes' => [
            'class' => ['claro-details__wrapper'],
          ],
          '#items' => [
            'Click on "Edit" from the operations dropdown on the page you want to fix',
            'Untoggle the “Display” state on top of the page - save',
            'Go back to the edit screen again and re-toggle the “Display” state on top of the page - save',
            'On the view page, click on customize and then on “Discard changes”',
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

}
