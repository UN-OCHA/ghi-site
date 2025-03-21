<?php

namespace Drupal\ghi_blocks\LayoutBuilder;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Traits\GinLbModalTrait;
use Drupal\gin_lb\HookHandler\FormAlter;
use Drupal\layout_builder\Form\LayoutRebuildConfirmFormBase;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to confirm the hiding of a block.
 */
abstract class ConfirmFormBase extends LayoutRebuildConfirmFormBase {

  use GinLbModalTrait;

  /**
   * The current region.
   *
   * @var string
   */
  protected $region;

  /**
   * The UUID of the block being removed.
   *
   * @var string
   */
  protected $uuid;

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
  public function buildForm(array $form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $uuid = NULL) {
    $this->region = $region;
    $this->uuid = $uuid;
    $form = parent::buildForm($form, $form_state, $section_storage, $delta);

    if ($this->isAjax()) {
      // We overwrite the class on purpose so that this is not a button anymore.
      $form['actions']['cancel']['#attributes']['class'] = ['dialog-cancel'];
    }

    if ($this->moduleHandler->moduleExists('gin_lb')) {
      $form['#after_build'][] = [FormAlter::class, 'afterBuildAttachGinLbForm'];
      $form['#gin_lb_form'] = TRUE;
      $form['#attributes']['class'][] = 'glb-form';
    }

    $this->makeGinLbForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('ghi_blocks.node.page_elements', ['node' => 14657]);
  }

}
