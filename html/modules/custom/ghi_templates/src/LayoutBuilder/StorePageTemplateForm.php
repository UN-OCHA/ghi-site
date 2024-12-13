<?php

namespace Drupal\ghi_templates\LayoutBuilder;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for storing a page template from a content page.
 */
class StorePageTemplateForm extends TemplateFormBase {

  use LayoutEntityHelperTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_store_page_template';
  }

  /**
   * Build form callback.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?EntityInterface $entity = NULL, ?SectionStorageInterface $section_storage = NULL) {
    $form = parent::buildForm($form, $form_state, $entity, $section_storage);

    $form_state->set('section_storage', $section_storage);
    $form_state->set('entity', $entity);

    $form['#title'] = $this->t('Save as a new page template based on @label', [
      '@label' => $section_storage->label(),
    ]);

    $form['settings']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
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
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create new template'),
    ];

    if ($this->isAjax()) {
      $form['actions']['submit']['#ajax']['callback'] = '::ajaxSubmit';
    }

    return $form;
  }

  /**
   * Validate callback for the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\ghi_templates\Entity\PageTemplate $page_template */
    $page_template = $this->entityTypeManager->getStorage('page_template')->create([
      'title' => $form_state->getValue('name'),
      'field_entity_reference' => $form_state->get('entity'),
    ]);
    $violations = $page_template->validate();
    foreach ($violations as $violation) {
      /** @var \Symfony\Component\Validator\ConstraintViolation $violation */
      $form_state->setError($form['settings']['name'], $violation->getMessage());
    }
  }

  /**
   * Submit callback for the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $page_template = $this->entityTypeManager->getStorage('page_template')->create([
      'title' => $form_state->getValue('name'),
      'field_entity_reference' => $form_state->get('entity'),
    ]);
    if ($page_template->save()) {
      $this->messenger()->addStatus($this->t('The page template <a href=":url">@label</a> has been saved.', [
        '@label' => $page_template->label(),
        ':url' => $page_template->toUrl('canonical')->toString(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('The page template @label could not be saved. Please contact an administrator.', [
        '@label' => $page_template->label(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $form_state->get('entity');
    $response = new AjaxResponse();
    $response->addCommand(new RedirectCommand($entity->toUrl()->toString()));
    $response->addCommand(new CloseDialogCommand('#layout-builder-modal'));
    return $response;
  }

}
