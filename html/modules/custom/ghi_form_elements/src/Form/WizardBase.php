<?php

namespace Drupal\ghi_form_elements\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base wizard form.
 */
abstract class WizardBase extends FormBase {

  use AjaxElementTrait;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The wrapper id for ajax.
   *
   * @var string
   */
  protected $ajaxWrapperId;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\ghi_form_elements\Form\WizardBase $instance */
    $instance = new static();
    $instance->moduleHandler = $container->get('module_handler');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    self::prepareAjaxForm($form, $form_state);
    $this->ajaxWrapperId = self::getWrapperId($form);
    $form['#prefix'] = '<div id="' . $this->ajaxWrapperId . '">';
    $form['#suffix'] = '</div>';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // We need to prepare the ajax form, because validation is called before
    // form building, and in case of errors doesn't reach the buildForm method.
    self::prepareAjaxForm($form, $form_state);
  }

}
