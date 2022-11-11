<?php

namespace Drupal\ghi_sections\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_element_sync\SyncBatch;
use Drupal\ghi_sections\SectionCreateBatch;
use Drupal\ghi_sections\SectionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A form for bulk creating sections.
 */
class SectionBulkCreate extends FormBase {

  /**
   * The section manager.
   *
   * @var \Drupal\ghi_sections\SectionManager
   */
  protected $sectionManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The supported bundles.
   *
   * @var array
   */
  protected $bundles;

  /**
   * Public constructor.
   */
  public function __construct(SectionManager $section_manager, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler) {
    $this->sectionManager = $section_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;

    $this->bundles = $this->sectionManager->getAvailableBaseObjectTypes();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ghi_sections.manager'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_sections_bulk_create_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $bundle_labels = array_map(function ($bundle) {
      return $bundle->label();
    }, $this->bundles);

    $form['bundle'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Bundle'),
      '#description' => $this->t('Select a bundle to limit the synching to specific pages.'),
      '#options' => $bundle_labels,
      '#multiple' => TRUE,
      '#required' => TRUE,
    ];
    foreach (array_keys($bundle_labels) as $bundle) {
      if ($bundle != 'plan') {
        $form['bundle'][$bundle]['#disabled'] = TRUE;
      }
    }

    $form['team'] = [
      '#type' => 'select',
      '#title' => $this->t('Team'),
      '#options' => $this->getTeamOptions(),
      '#description' => $this->t('Select the team that should be initially responsible for the sections.'),
      '#required' => TRUE,
    ];

    $form['recreate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Recreate'),
      '#description' => $this->t('Check this if existing sections should be recreated.'),
    ];

    $form['reset'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reset'),
      '#description' => $this->t('Check this if existing sections should be reset to their initial state (empty page).'),
    ];

    if ($this->moduleHandler->moduleExists('ghi_element_sync')) {
      $form['sync_elements'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Sync elements'),
        '#description' => $this->t('Check this if elements should be synced from Hum Insights.'),
      ];
      $form['sync_elements_cleanup'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Cleanup any existing elements'),
        '#description' => $this->t('Check this if existing elements should be removed before synching from remote.'),
        '#states' => [
          'visible' => [
            ':input[name="sync_elements"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Bulk-create sections'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $batch_builder = new BatchBuilder();
    $batch_builder
      ->setFinishCallback([SectionCreateBatch::class, 'finish'])
      ->setTitle($this->t('Creating sections'))
      ->setInitMessage($this->t('Starting section creation.'))
      ->setErrorMessage($this->t('Section creation has encountered an error.'));

    $bundle = array_keys(array_filter($form_state->getValue('bundle')));
    if (empty($bundle)) {
      $bundle = array_keys($this->bundles);
    }

    $batch_builder->addOperation([SectionCreateBatch::class, 'process'], [
      $this->sectionManager,
      $bundle,
      $form_state->getValue('team'),
      $form_state->getValue('recreate'),
      $form_state->getValue('reset'),
    ]);

    $sync_elements = $form_state->hasValue('sync_elements') && $form_state->getValue('sync_elements');
    if ($sync_elements && $sync_manager = $this->getSyncManager()) {
      $sync_bundle = [
        'section',
      ];
      if (in_array('plan', $bundle)) {
        $sync_bundle[] = 'plan_cluster';
      }
      $batch_builder->addOperation([SyncBatch::class, 'process'], [
        $sync_manager,
        $sync_bundle,
        [],
        TRUE,
        TRUE,
        !$form_state->getValue('recreate'),
        $form_state->getValue('sync_elements_cleanup') ?? FALSE,
      ]);
    }

    batch_set($batch_builder->toArray());

  }

  /**
   * Retrieve the team options for the team select field.
   *
   * @return array
   *   An array of team names, keyed by tid.
   */
  protected function getTeamOptions() {
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('team');
    if (empty($terms)) {
      return [];
    }
    $options = [];
    foreach ($terms as $term) {
      $options[$term->tid] = $term->name;
    }
    return $options;
  }

  /**
   * Get the element sync manager if available.
   *
   * @return \Drupal\ghi_element_sync\SyncManager|null
   *   The sync manager service or NULL.
   */
  protected static function getSyncManager() {
    $service_id = 'ghi_element_sync.sync_elements';
    return \Drupal::hasService($service_id) ? \Drupal::service($service_id) : NULL;
  }

}
