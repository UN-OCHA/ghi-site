<?php

namespace Drupal\ghi_templates\LayoutBuilder;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Render\Markup;
use Drupal\ghi_blocks\Interfaces\ConfigValidationInterface;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutBuilderHighlightTrait;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for exporting page config.
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
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

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
    $instance->blockManager = $container->get('plugin.manager.block');
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
   * Builds the form for the block import.
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
  public function buildForm(array $form, FormStateInterface $form_state, EntityInterface $entity = NULL, SectionStorageInterface $section_storage = NULL) {
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
  private function importForm($form, FormStateInterface $form_state, SectionStorageInterface $section_storage = NULL) {

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
      '#weight' => -1,
      '#attributes' => [
        'class' => [
          'dialog-cancel',
        ],
      ],
    ];

    return $form;
  }

  /**
   * Build the summary form where the code can be inserted.
   */
  private function confirmForm($form, FormStateInterface $form_state, EntityInterface $entity = NULL, SectionStorageInterface $section_storage = NULL) {
    $form_state->set('section_storage', $section_storage);
    $form_state->set('entity', $entity);
    $page_config = $form_state->get('page_config');
    $page_config = array_filter($page_config, function ($section) {
      return !empty($section['components']);
    });

    $header = [
      $this->t('Element type'),
      $this->t('Label'),
      $this->t('Configuration issues'),
    ];

    $rows = [];
    foreach ($page_config as $section_key => $section) {
      if (count($page_config) > 1) {
        $rows[] = [
          [
            'data' => [
              '#markup' => (string) Markup::create('<h2>' . $section['layout_settings']['label'] . '</h2>'),
            ],
            'colspan' => 2,
          ],
          '#disabled' => TRUE,
        ];
      }
      foreach ($section['components'] as $component_key => $component) {
        /** @var \Drupal\Core\Block\BlockBase $block */
        $block = $this->blockManager->createInstance($component['configuration']['id'], $component['configuration']);
        $block->setContext('entity', EntityContext::fromEntity($entity, $entity->type->entity->label()));
        $rows[$section_key . '--' . $component_key] = [
          $block->getPluginDefinition()['admin_label'],
          $block->label() ?? $this->t('n/a'),
          $block instanceof ConfigValidationInterface && !$block->validateConfiguration() ? Markup::create(implode('<br />', $block->getConfigErrors())) : '',
        ];
      }
    }

    $form['settings']['explanation'] = [
      '#type' => 'markup',
      '#markup' => $this->t('On this screen you can preview which page elements should be imported from the template. When you click on "Import", the template will be applied and you will be redirected to the customize screen of the page, where you can do further modifications before saving the page.<br />If you see any errors in the column "Configuration issues", these will be attempted to be corrected automatically, but need a manual review after the elements have been added to the page.'),
    ];

    $form['settings']['summary'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Select the elements that you want to import.'),
    ];
    $form['settings']['summary']['table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#default_value' => array_map(function ($option) {
        return TRUE;
      }, $rows),
      '#required' => TRUE,
    ];

    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
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
      '#description' => $this->t('If checked, this will remove all existing page elements from the current page before doing the import. If unchecked, the imported configuration will be appended to the current page instead.'),
      '#default_value' => TRUE,
    ];

    return $form;
  }

  /**
   * Get a previously submitted plugin configuration from the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array|null
   *   The submitted or stored value.
   */
  private function getSubmittedConfig(FormStateInterface $form_state) {
    return $form_state->getValue('config') ?? ($form_state->get('config') ? Yaml::encode($form_state->get('config')) : NULL);
  }

  /**
   * Validate handler for the import form.
   */
  public function validateForm(&$form, FormStateInterface $form_state) {
    $action = end($form_state->getTriggeringElement()['#parents']);
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
      // General idea: Setup the tempstore and redirect to the page in edit
      // mode.
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
