<?php

namespace Drupal\ghi_templates\LayoutBuilder;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Interfaces\ConfigValidationInterface;
use Drupal\ghi_templates\Entity\PageTemplateInterface;
use Drupal\ghi_templates\PageConfigTrait;
use Drupal\hpc_common\Traits\AjaxFormTrait;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for applying a page template to a content page.
 */
class ApplyPageTemplateForm extends TemplateFormBase {

  use LayoutRebuildTrait;
  use AjaxHelperTrait;
  use AjaxFormTrait;
  use PageConfigTrait;

  /**
   * The form steps for the import wizard.
   */
  const STEPS = [
    'select_template',
    'confirm',
  ];

  /**
   * The page template manager service.
   *
   * @var \Drupal\ghi_templates\PageTemplateManager
   */
  protected $pageTemplateManager;

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
    $instance->pageTemplateManager = $container->get('ghi_templates.manager');
    $instance->blockManager = $container->get('plugin.manager.block');
    $instance->layoutTempstoreRepository = $container->get('layout_builder.tempstore_repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_apply_page_template';
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
    if ($entity === NULL || $section_storage === NULL) {
      return $form;
    }

    $form['#title'] = $this->t('Apply a page template for @label', [
      '@label' => $entity->label(),
    ]);

    $steps = self::STEPS;
    $current_step = $form_state->get('current_import_step') ?? reset($steps);

    switch ($current_step) {
      case 'select_template':
        $form = $this->selectTemplateForm($form, $form_state, $entity, $section_storage);
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

    return $form;
  }

  /**
   * Build the import form where the code can be inserted.
   */
  private function selectTemplateForm($form, FormStateInterface $form_state, EntityInterface $entity, SectionStorageInterface $section_storage) {
    $page_template_options = array_map(function (PageTemplateInterface $page_template) {
      return [
        'title' => $page_template->label(),
        'source' => $page_template->getSourceSummary('@entity_type: @source', FALSE),
      ];
    }, $this->pageTemplateManager->loadAvailableTemplatesForEntity($entity));

    if (!empty($page_template_options)) {
      $form['settings']['explanation'] = [
        '#type' => 'container',
        0 => [
          '#type' => 'markup',
          '#markup' => $this->t('Select a template to apply it to the current page. Before the changes take effect, you will see a summary of the newly created page elements in the next step. You can also choose to add the page elements from the template to the existing elements on the page, or to overwrite the page completely. If the page template is associated with a base object, it will be used for previewing of the element content.') . '<br /><br />',
        ],
      ];
    }

    $form['settings']['page_template'] = [
      '#type' => 'tableselect',
      '#multiple' => FALSE,
      '#header' => [
        'title' => $this->t('Title'),
        'source' => $this->t('Source page'),
      ],
      '#options' => $page_template_options,
      '#empty' => new TranslatableMarkup('<p>There no page templates available to apply to the current page. You can see a list of available templates on the <a href="@url_collection">Page templates</a> page.</p><p>You can create a page template either manually using the <a href="@url_add">Add page template</a> page, or create one from any supported content page using the frontend controls.</p>', [
        '@url_collection' => Url::fromRoute('entity.page_template.collection')->toString(),
        '@url_add' => Url::fromRoute('entity.page_template.add_page')->toString(),
      ]),
      '#default_value' => array_key_first($page_template_options),
    ];

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

    $form['actions']['validate'] = [
      '#type' => 'submit',
      '#value' => $this->t('Validate'),
      '#disabled' => empty($page_template_options),
    ];
    if ($this->isAjax()) {
      $form['actions']['validate']['#ajax']['rebuild'] = TRUE;
      $form['actions']['validate']['#ajax']['callback'] = '::ajaxSubmit';
      $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);
    }

    return $form;
  }

  /**
   * Build the summary form where the code can be inserted.
   */
  private function confirmForm($form, FormStateInterface $form_state, EntityInterface $entity, SectionStorageInterface $section_storage) {
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
    $form['settings']['overwrite'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Clear layout'),
      '#description' => $this->t('If checked, this will remove all existing page elements from the current page before doing the import. If unchecked, the imported configuration will be appended to the current page instead.'),
      '#default_value' => TRUE,
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
   * @return \Drupal\ghi_templates\Entity\PageTemplateInterface|null
   *   The submitted or stored page template.
   */
  private function getSubmittedPageTemplate(FormStateInterface $form_state) {
    $id = $form_state->getValue('page_template') ?? ($form_state->get('page_template') ?? NULL);
    return $id ? $this->pageTemplateManager->loadPageTemplate($id) : NULL;
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
      $page_template = $this->getSubmittedPageTemplate($form_state);
      $section_storage = $this->getSectionStorageForEntity($page_template);
      $import_config = $this->exportSectionStorage($section_storage);
      $form_state->set('page_template', $page_template?->id());
      $form_state->set('page_config', $import_config['page_config'] ?? NULL);
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
      $section_storage = $this->buildSectionStorageFromPageConfig($section_storage, $entity, $page_config, $overwrite, $selected_elements);

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
