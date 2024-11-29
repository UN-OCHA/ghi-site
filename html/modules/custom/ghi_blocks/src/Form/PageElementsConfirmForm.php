<?php

namespace Drupal\ghi_blocks\Form;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Traits\GinLbModalTrait;
use Drupal\ghi_blocks\Traits\PageElementsTrait;
use Drupal\gin_lb\HookHandler\FormAlter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to actions on the page elements listing.
 */
class PageElementsConfirmForm extends ConfirmFormBase {

  use GinLbModalTrait;
  use AjaxFormHelperTrait;
  use PageElementsTrait;

  /**
   * The entity object.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The uuid of the page element to act on.
   *
   * @var string
   */
  protected $uuid;

  /**
   * The action to execute.
   *
   * @var string
   */
  protected $action;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_blocks_page_elements_action_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    if ($this->action == 'remove') {
      return $this->t('This will permanently remove the page element from this page. This cannot be undone.');
    }
    return $this->t('This will @action the page element on this page without removing it permanently.', [
      '@action' => $this->action,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to @action the page element?', [
      '@action' => $this->action,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('@action the page element', [
      '@action' => ucfirst($this->action),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function processAction() {
    $this->actionComponentOnEntity($this->action, $this->entity, [$this->uuid]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?EntityInterface $entity = NULL, $uuid = NULL, $action = NULL) {
    $this->entity = $entity;
    $this->uuid = $uuid;
    $this->action = $action;

    $form = parent::buildForm($form, $form_state);

    if ($this->isAjax()) {
      $form['actions']['submit']['#ajax']['callback'] = '::ajaxSubmit';
      // We overwrite the class on purpose so that this is not a button anymore.
      $form['actions']['cancel']['#attributes']['class'] = ['dialog-cancel'];

      // The AJAX system automatically moves focus to the first tabbable
      // element after closing a dialog, sometimes scrolling to a page top.
      // Disable refocus on the button.
      $form['actions']['submit']['#ajax']['disable-refocus'] = TRUE;
    }

    // Mark this as an administrative page for JavaScript ("Back to site" link).
    $form['#attached']['drupalSettings']['path']['currentPathIsAdmin'] = TRUE;

    if ($this->moduleHandler->moduleExists('gin_lb')) {
      $form['#after_build'][] = [FormAlter::class, 'afterBuildAttachGinLbForm'];
      $form['#gin_lb_form'] = TRUE;
      $form['#attributes']['class'][] = 'glb-form';
    }

    $form['#attached']['library'][] = 'system/admin';
    $form['#attached']['library'][] = 'ghi_blocks/layout_builder_gin';
    $form['#attached']['library'][] = 'ghi_blocks/layout_builder_modal_admin';

    $this->makeGinLbForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('ghi_blocks.node.page_elements', [
      'node' => $this->entity->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->processAction();
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new RedirectCommand($this->getCancelUrl()->toString()));
    $response->addCommand(new CloseDialogCommand('#layout-builder-modal'));
    return $response;
  }

}
