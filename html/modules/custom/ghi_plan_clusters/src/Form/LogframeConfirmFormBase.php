<?php

namespace Drupal\ghi_plan_clusters\Form;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Traits\GinLbModalTrait;
use Drupal\gin_lb\HookHandler\FormAlter;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to confirm the hiding of a block.
 */
abstract class LogframeConfirmFormBase extends ConfirmFormBase {

  use GinLbModalTrait;
  use AjaxFormHelperTrait;

  /**
   * The node object.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The logframe manager.
   *
   * @var \Drupal\ghi_plan_clusters\PlanClusterManager
   */
  protected $planClusterManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->moduleHandler = $container->get('module_handler');
    $instance->planClusterManager = $container->get('ghi_plan_clusters.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_plan_clusters_logframe_action';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $this->node = $node;

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
    return Url::fromRoute('ghi_subpages.node.pages', [
      'node' => $this->node->id(),
    ]);
  }

  /**
   * Process the logframe action after confirmation.
   */
  abstract public function processLogframeAction();

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->processLogframeAction();
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
