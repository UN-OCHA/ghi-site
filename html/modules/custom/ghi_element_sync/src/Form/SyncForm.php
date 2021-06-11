<?php

namespace Drupal\ghi_element_sync\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_element_sync\SyncBatch;
use Drupal\ghi_element_sync\SyncManager;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A form for syncing page elements of of all plan nodes from a remote source.
 */
class SyncForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\ghi_element_sync\SyncManager
   */
  protected $syncManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The supported bundles.
   *
   * @var array
   */
  protected $bundles;

  /**
   * Public constructor.
   */
  public function __construct(SyncManager $sync_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->syncManager = $sync_manager;
    $this->entityTypeManager = $entity_type_manager;

    $this->bundles = [
      'plan',
      'plan_entity',
      'governing_entity',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ghi_element_sync.sync_elements'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_element_sync_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $form['#node'] = $node;

    $bundle_labels = array_map(function ($bundle) {
      return $this->entityTypeManager->getStorage('node_type')->load($bundle)->label();
    }, $this->bundles);

    $form['limit'] = [
      '#type' => 'select',
      '#title' => $this->t('Limit'),
      '#description' => $this->t('Optionally limit the element sync process. By default the following node types will be processed: @types. If none is selected, nodes of all bundles will be processed.', [
        '@types' => implode(', ', $bundle_labels),
      ]),
      '#options' => [
        'none' => $this->t('No limit'),
        'bundle' => $this->t('Bundle'),
        'id' => $this->t('ID'),
      ],
    ];

    $form['bundle'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Bundle'),
      '#description' => $this->t('Select a bundle to limit the synching to specific pages.'),
      '#options' => array_combine($this->bundles, $bundle_labels),
      '#multiple' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="limit"]' => ['value' => 'bundle'],
        ],
      ],
    ];

    $form['id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Limit to ids'),
      '#description' => $this->t('Enter a comma separated list of node ids to limit the synching to specific pages.'),
      '#states' => [
        'visible' => [
          ':input[name="limit"]' => ['value' => 'id'],
        ],
      ],
    ];

    $form['revisions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create new revisions'),
      '#description' => $this->t('Check this if new revisions should be created.'),
      '#default_value' => FALSE,
    ];

    $form['sync_elements'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync all elements'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $batch_builder = new BatchBuilder();
    $batch_builder
      ->setFinishCallback([SyncBatch::class, 'finish'])
      ->setTitle($this->t('Synching elements'))
      ->setInitMessage($this->t('Starting element sync.'))
      ->setErrorMessage($this->t('Element sync has encountered an error.'));

    $limit = $form_state->getValue('limit');
    $bundle = $this->bundles;
    $id = NULL;
    $revisions = $form_state->getValue('revisions');

    if ($limit == 'bundle') {
      $bundle = array_keys(array_filter($form_state->getValue('bundle')));
      if (empty($bundle)) {
        $bundle = $this->bundles;
      }
    }
    elseif ($limit == 'id') {
      $id = array_filter(array_map(function ($item) {
        return (int) trim($item);
      }, explode(',', $form_state->getValue('id'))));
    }

    $batch_builder->addOperation([SyncBatch::class, 'process'], [
      $this->syncManager,
      $bundle,
      (array) $id,
      $revisions,
    ]);
    batch_set($batch_builder->toArray());

  }

}
