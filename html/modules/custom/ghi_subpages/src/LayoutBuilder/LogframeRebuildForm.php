<?php

namespace Drupal\ghi_subpages\LayoutBuilder;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_blocks\Traits\GinLbModalTrait;
use Drupal\ghi_subpages\Entity\LogframeSubpage;
use Drupal\hpc_common\Traits\AjaxFormTrait;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutBuilderHighlightTrait;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder_ipe\Controller\EntityEditController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for rebuilding a logframe page.
 */
class LogframeRebuildForm extends FormBase {

  use LayoutEntityHelperTrait;
  use LayoutBuilderHighlightTrait;
  use LayoutRebuildTrait;
  use GinLbModalTrait;
  use AjaxHelperTrait;
  use AjaxFormTrait;
  use StringTranslationTrait;

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
   * The logframe manager.
   *
   * @var \Drupal\ghi_subpages\LogframeManager
   */
  protected $logframeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->blockManager = $container->get('plugin.manager.block');
    $instance->layoutTempstoreRepository = $container->get('layout_builder.tempstore_repository');
    $instance->contextHandler = $container->get('context.handler');
    $instance->controllerResolver = $container->get('controller_resolver');
    $instance->logframeManager = $container->get('ghi_subpages.logframe_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_logframe_rebuild';
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

    if (!$entity instanceof LogframeSubpage) {
      return $form;
    }

    $form_state->set('section_storage', $section_storage);
    $form_state->set('entity', $entity);
    $form['#title'] = $this->t('Rebuild logframe page for @label', [
      '@label' => $entity->getParentNode()->label(),
    ]);

    $form['settings'] = [
      '#type' => 'container',
    ];

    $plan_entity_types = $this->logframeManager->getEntityTypesFromNode($entity);
    $form['settings']['message'] = [
      '#type' => 'markup',
      '#markup' => new TranslatableMarkup('The logframe page will be completely rebuild, removing all existing elements on the page, including ones that have been manually added and configured.<br />Logframe page elements for these levels will be created: <em>@plan_entities</em><br /><br />Rebuilding the logframe will take some time. Please do not close this browser window while the rebuilding is in progress.', [
        '@plan_entities' => implode(', ', $plan_entity_types),
      ]),
    ];

    $form['actions'] = [
      '#type' => 'container',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Rebuild'),
    ];
    if ($this->isAjax()) {
      $form['actions']['submit']['#ajax']['rebuild'] = TRUE;
      $form['actions']['submit']['#ajax']['callback'] = '::ajaxSubmit';
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
   * Submit handler for the import form.
   *
   * @todo Document what this does.
   */
  public function submitForm(&$form, FormStateInterface $form_state) {
    $section_storage = $form_state->get('section_storage');
    $entity = $form_state->get('entity');

    $section_storage = $this->logframeManager->setupLogframePage($entity, $section_storage);

    // Then put all that into the tempstore, so that it's available once the
    // layout is edited.
    $this->layoutTempstoreRepository->set($section_storage);
    $form_state->set('section_storage', $section_storage);
  }

}
