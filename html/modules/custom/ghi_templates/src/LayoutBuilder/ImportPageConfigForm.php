<?php

namespace Drupal\ghi_templates\LayoutBuilder;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutBuilderHighlightTrait;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for importing page configuration.
 */
class ImportPageConfigForm extends TemplateFormBase {

  use LayoutEntityHelperTrait;
  use LayoutBuilderHighlightTrait;
  use LayoutRebuildTrait;

  /**
   * The form steps for the import wizard.
   */
  const STEPS = [
    'import',
    'confirm',
  ];

  /**
   * Layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->layoutTempstoreRepository = $container->get('layout_builder.tempstore_repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_import_page_config';
  }

  /**
   * Builds the page configuration import form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being configured.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage being configured.
   *
   * @return array
   *   The form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?EntityInterface $entity = NULL, ?SectionStorageInterface $section_storage = NULL) {
    $form = parent::buildForm($form, $form_state, $entity, $section_storage);

    $form['#title'] = $this->t('Import page configuration to @label', [
      '@label' => $entity->label(),
    ]);

    $steps = self::STEPS;
    $current_step = $form_state->get('current_import_step') ?? reset($steps);

    switch ($current_step) {
      case 'import':
        $form = $this->importForm($form, $form_state, $section_storage);
        break;

      case 'confirm':
        $form = $this->confirmForm($form, $form_state, $entity, $section_storage);
        break;
    }

    if ($this->isAjax()) {
      $form['actions']['submit']['#ajax']['rebuild'] = FALSE;
      $form['actions']['submit']['#ajax']['callback'] = '::ajaxSubmit';
    }

    return $form;
  }

  /**
   * Build the import form where the code can be inserted.
   */
  private function importForm($form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL) {

    $config = $this->getSubmittedConfig($form_state);

    $form['settings']['config'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Import from code'),
      '#default_value' => $config,
      '#rows' => 10,
    ];

    $form['actions']['validate'] = [
      '#type' => 'submit',
      '#value' => $this->t('Validate'),
    ];
    if ($this->isAjax()) {
      $form['actions']['validate']['#ajax']['rebuild'] = TRUE;
      $form['actions']['validate']['#ajax']['callback'] = '::ajaxSubmit';
    }

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $section_storage->getLayoutBuilderUrl(),
      '#attributes' => [
        'class' => [
          'dialog-cancel',
        ],
      ],
    ];

    return $form;
  }

  /**
   * Builds the element selection and confirmation form.
   */
  private function confirmForm($form, FormStateInterface $form_state, ?EntityInterface $entity = NULL, ?SectionStorageInterface $section_storage = NULL) {
    $form_state->set('section_storage', $section_storage);
    $form_state->set('entity', $entity);

    $form['settings']['explanation'] = [
      '#type' => 'markup',
      '#markup' => $this->t('On this screen you can preview which page elements should be imported from the template. When you click on "Import", the template will be applied and you will be redirected to the customize screen of the page, where you can do further modifications before saving the page.<br />If you see any errors in the column "Configuration issues", these will be attempted to be corrected automatically, but need a manual review after the elements have been added to the page.'),
    ];

    $form['settings']['summary'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Select the elements that you want to import.'),
    ];
    $form['settings']['summary']['table'] = $this->buildElementSelectionTable($form_state->get('page_config'), $entity, $section_storage);

    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      // Core requires an explicit submit handler for limited validation.
      '#limit_validation_errors' => [],
      '#submit' => ['::submitForm'],
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
    ];
    if ($this->isAjax()) {
      $form['actions']['back']['#ajax']['rebuild'] = TRUE;
      $form['actions']['back']['#ajax']['callback'] = '::ajaxSubmit';
      $form['actions']['submit']['#ajax']['callback'] = '::ajaxSubmit';
    }

    $form['settings']['overwrite'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Clear layout'),
      '#description' => $this->t('If checked, this will remove all existing (and removable) page elements from the current page before doing the import. If unchecked, the imported configuration will be appended to the current page instead.'),
      '#default_value' => TRUE,
    ];

    return $form;
  }

  /**
   * Gets the submitted or stored YAML configuration.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return string|null
   *   The submitted YAML or the stored configuration encoded as YAML.
   */
  private function getSubmittedConfig(FormStateInterface $form_state) {
    return $form_state->getValue('config') ?? ($form_state->get('config') ? Yaml::encode($form_state->get('config')) : NULL);
  }

  /**
   * Validate handler for the import form.
   */
  public function validateForm(&$form, FormStateInterface $form_state) {
    $action = end($form_state->getTriggeringElement()['#parents']);
    // Navigation must not re-parse input or validate an empty selection table.
    if ($action === 'back') {
      return;
    }
    if ($action == 'submit') {
      return parent::validateForm($form, $form_state);
    }
    else {
      $config = $this->getSubmittedConfig($form_state);
      $import_config = Yaml::decode($config);
      $form_state->set('config', $import_config);
      $form_state->set('page_config', $import_config['page_config'] ?? NULL);
      if (!is_array($import_config) || empty($import_config['entity_type']) || empty($import_config['bundle']) || empty($import_config['page_config']) || empty($import_config['hash'])) {
        $form_state->setErrorByName('config', $this->t('Empty or malformed configuration.'));
      }
      elseif ($import_config['hash'] != md5(Yaml::encode(ArrayHelper::mapObjectsToString(array_diff_key($import_config, ['hash' => TRUE]))))) {
        $form_state->setErrorByName('config', $this->t('Internal validation failed.'));
      }
      elseif (count($import_config['page_config']) > 1 || array_key_exists('validation', $import_config) && !$import_config['validation']) {
        $form_state->setErrorByName('config', $this->t('Configuration cannot be imported due to misconfigured sections.'));
      }
    }
  }

  /**
   * Submit handler for the import form.
   *
   * @todo Document what this does.
   */
  public function submitForm(&$form, FormStateInterface $form_state) {
    $action = end($form_state->getTriggeringElement()['#parents']);
    if ($action == 'submit') {
      // Prepare the layout in tempstore. The AJAX callback refreshes the editor
      // or redirects to the page when requested.
      $overwrite = (bool) $form_state->getValue('overwrite');
      $selected_elements = $form_state->getValue('table');

      /** @var \Drupal\layout_builder\SectionStorageInterface $section_storage */
      $section_storage = $form_state->get('section_storage');
      $entity = $form_state->get('entity');
      $page_config = $form_state->get('page_config');
      $section_storage = $this->pageTemplateManager->buildSectionStorageFromPageConfig($section_storage, $entity, $page_config, $overwrite, $selected_elements);

      // Then put all that into the tempstore, so that it's available once the
      // layout is edited.
      $this->layoutTempstoreRepository->set($section_storage);
      $form_state->set('section_storage', $section_storage);
      return;
    }

    $steps = self::STEPS;
    $pos = array_search($action, $steps);

    if ($action == 'back') {
      $form_state->set('current_import_step', $steps[$pos - 1] ?? reset($steps));
    }
    else {
      $form_state->set('current_import_step', $steps[$pos + 1] ?? $action);
    }

    $form_state->setRebuild();
  }

}
