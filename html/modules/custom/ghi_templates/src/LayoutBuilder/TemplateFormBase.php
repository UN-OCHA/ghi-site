<?php

namespace Drupal\ghi_templates\LayoutBuilder;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Traits\GinLbModalTrait;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder_ipe\Controller\EntityEditController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for template forms.
 */
abstract class TemplateFormBase extends FormBase {

  use LayoutEntityHelperTrait;
  use GinLbModalTrait;

  /**
   * The controller resolver.
   *
   * @var \Drupal\Core\Controller\ControllerResolverInterface
   */
  protected $controllerResolver;

  /**
   * The template manager.
   *
   * @var \Drupal\ghi_templates\PageTemplateManager
   */
  protected $pageTemplateManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->controllerResolver = $container->get('controller_resolver');
    $instance->pageTemplateManager = $container->get('ghi_templates.manager');
    return $instance;
  }

  /**
   * Build form callback.
   */
  public function buildForm(array $form, FormStateInterface $form_state, EntityInterface $entity = NULL, SectionStorageInterface $section_storage = NULL) {
    $form['settings'] = [
      '#type' => 'container',
    ];
    $form['actions'] = [
      '#type' => 'container',
    ];
    $form['#attached']['library'][] = 'ghi_templates/template_modal_form';
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
      $callable = $this->controllerResolver->getControllerFromDefinition(EntityEditController::class . '::edit');
      $response = $callable($form_state->get('section_storage'));
      $response->addCommand(new CloseDialogCommand('#layout-builder-modal'));
    }
    return $response;
  }

}
