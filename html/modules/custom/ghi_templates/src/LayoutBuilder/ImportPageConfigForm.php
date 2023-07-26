<?php

namespace Drupal\ghi_templates\LayoutBuilder;

use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Render\Markup;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_blocks\Traits\GinLbModalTrait;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\hpc_common\Traits\AjaxFormTrait;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutBuilderHighlightTrait;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\Plugin\SectionStorage\DefaultsSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder_ipe\Controller\EntityEditController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for exporting page config.
 */
class ImportPageConfigForm extends FormBase {

  use LayoutEntityHelperTrait;
  use LayoutBuilderHighlightTrait;
  use LayoutRebuildTrait;
  use GinLbModalTrait;
  use AjaxHelperTrait;
  use AjaxFormTrait;

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
   * The plugin context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * The controller resolver.
   *
   * @var \Drupal\Core\Controller\ControllerResolverInterface
   */
  protected $controllerResolver;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->blockManager = $container->get('plugin.manager.block');
    $instance->layoutTempstoreRepository = $container->get('layout_builder.tempstore_repository');
    $instance->contextHandler = $container->get('context.handler');
    $instance->controllerResolver = $container->get('controller_resolver');
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
      // @todo static::ajaxSubmit() requires data-drupal-selector to be the same
      //   between the various Ajax requests. A bug in
      //   \Drupal\Core\Form\FormBuilder prevents that from happening unless
      //   $form['#id'] is also the same. Normally, #id is set to a unique HTML
      //   ID via Html::getUniqueId(), but here we bypass that in order to work
      //   around the data-drupal-selector bug. This is okay so long as we
      //   assume that this form only ever occurs once on a page. Remove this
      //   workaround in https://www.drupal.org/node/2897377.
      $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);
    }

    $this->makeGinLbForm($form, $form_state);
    return $form;
  }

  /**
   * Build the import form where the code can be inserted.
   */
  private function importForm($form, FormStateInterface $form_state, SectionStorageInterface $section_storage = NULL) {

    $form['settings'] = [
      '#type' => 'container',
    ];

    $config = $this->getSubmittedConfig($form_state);

    $form['settings']['config'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Import from code'),
      '#default_value' => $config,
      '#rows' => 10,
    ];

    $form['actions'] = [
      '#type' => 'container',
    ];
    $form['actions']['validate'] = [
      '#type' => 'submit',
      '#value' => $this->t('Validate'),
    ];
    if ($this->isAjax()) {
      $form['actions']['validate']['#ajax']['rebuild'] = TRUE;
      $form['actions']['validate']['#ajax']['callback'] = '::ajaxSubmit';
      $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);
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
        $rows[$section_key . '--' . $component_key] = [
          $block->getPluginDefinition()['admin_label'],
          $block->label() ?? $this->t('n/a'),
        ];
      }
    }

    $form['settings'] = [
      '#type' => 'container',
    ];

    $form['settings']['overwrite'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Clear layout'),
      '#description' => $this->t('If checked, this will remove all existing page elements from the current page before doing the import. If unchecked, the imported configuration will be appended to the current page instead.'),
      '#default_value' => FALSE,
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

    $form['actions'] = [
      '#type' => 'container',
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
      $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);
    }

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
   * Build and send an ajax response after successfull form submission.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response.
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    $query = $this->getRequest()->query;
    $redirect_to_entity = $query->get('redirect_to_entity', FALSE);
    if ($redirect_to_entity) {
      /** @var \Drupal\Core\Url $entity_url */
      $entity_url = $form_state->get('entity')->toUrl();
      $query = $entity_url->getOption('query');
      $query['openIpe'] = TRUE;
      $entity_url->setOption('query', $query);
      $response = new AjaxResponse();
      $response->addCommand(new RedirectCommand($entity_url->toString()));
    }
    else {
      /** @var \Drupal\layout_builder_ipe\Controller\EntityEditController $entity_edit_controller */
      $entity_edit_controller = $this->controllerResolver->getControllerFromDefinition(EntityEditController::class);
      $response = $entity_edit_controller->edit($form_state->get('section_storage'));
      $response->addCommand(new CloseDialogCommand('#layout-builder-modal'));
    }
    return $response;
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
      // First, make sure we have an overridden section storage.
      if ($section_storage instanceof DefaultsSectionStorage) {
        // Overide the section storage in the tempstore.
        $section_storage = $this->sectionStorageManager()->load('overrides', [
          'entity' => EntityContext::fromEntity($entity),
        ]);
      }

      // Clear the current storage.
      if ($overwrite) {
        $section_storage->removeAllSections();
      }

      // Now setup each section according to the imported config.
      $page_config = $form_state->get('page_config');
      foreach ($page_config as $section_key => $section_config) {
        if (empty($section_config['components'])) {
          continue;
        }
        $components = [];
        foreach ($section_config['components'] as $component_key => $component_config) {
          if (empty($selected_elements[$section_key . '--' . $component_key])) {
            continue;
          }
          $component = SectionComponent::fromArray($component_config);
          $plugin = $component->getPlugin();
          $definition = $plugin->getPluginDefinition();
          $context_mapping = [
            'context_mapping' => array_intersect_key([
              'node' => 'layout_builder.entity',
            ], $definition['context_definitions']),
          ];
          // And make sure that base objects are mapped too.
          $base_objects = BaseObjectHelper::getBaseObjectsFromNode($entity) ?? [];
          foreach ($base_objects as $_base_object) {
            $contexts = [
              EntityContext::fromEntity($_base_object),
            ];
            foreach ($definition['context_definitions'] as $context_key => $context_definition) {
              $matching_contexts = $this->contextHandler->getMatchingContexts($contexts, $context_definition);
              if (empty($matching_contexts)) {
                continue;
              }
              $context_mapping['context_mapping'][$context_key] = $_base_object->getUniqueIdentifier();
            }
          }
          $configuration = $context_mapping + $component->get('configuration');
          $component->setConfiguration($configuration);
          $components[$component->getUuid()] = $component->toArray();
        }
        if ($overwrite || empty($section_storage->getSections())) {
          $section_config['components'] = $components;
          $section = Section::fromArray($section_config);
          $section_storage->appendSection($section);
        }
        else {
          $section = $section_storage->getSection(0);
          foreach ($components as $component) {
            $section->appendComponent(SectionComponent::fromArray($component));
          }
        }
      }

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
