<?php

namespace Drupal\ghi_sections\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a wizard form for creating section nodes.
 */
class SectionWizard extends FormBase {

  use AjaxElementTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a SubpagesPages form.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_sections_wizard';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $wrapper_id = self::getWrapperId($form);
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';

    $action = self::getActionFromFormState($form_state);
    if ($action === 'back') {
      $values = array_intersect_key($form_state->getValues(), array_flip([
        'type',
        'base_object',
      ]));
      if (!empty($values['base_object'])) {
        $form_state->setValue('base_object', NULL);
      }
      elseif (!empty($values['type'])) {
        $form_state->setValue('type', NULL);
      }
    }

    // Select the base object.
    $base_object_types = $this->entityTypeManager->getStorage('base_object_type')->loadMultiple();
    $base_object = NULL;
    if ($form_state->hasValue('base_object')) {
      $base_object = $this->entityTypeManager->getStorage('base_object')->load($form_state->getValue('base_object')[0]['target_id']);
    }

    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Section type'),
      '#description' => $this->t('Select a section type.'),
      '#options' => array_map(function ($type) {
        return $type->label();
      }, $base_object_types),
      '#default_value' => $form_state->hasValue('type') ? $form_state->getValue('type') : NULL,
      '#disabled' => $form_state->hasValue('type'),
    ];

    if ($form_state->hasValue('type')) {
      $form['base_object'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'base_object',
        '#title' => $this->t('Base object'),
        '#description' => $this->t('Select a base object for this section.'),
        '#default_value' => $base_object,
        '#tags' => TRUE,
        '#selection_settings' => [
          'target_bundles' => [$form_state->getValue('type')],
        ],
        '#disabled' => $base_object,
        '#required' => TRUE,
      ];
    }

    if ($base_object) {
      $form['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title'),
        '#description' => $this->t('Optional: Change the title for this section'),
        '#default_value' => $base_object->label(),
        '#required' => TRUE,
      ];

      if ($base_object->bundle() != 'plan') {
        $form['year'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Year'),
          '#description' => $this->t('Enter a year for this section'),
          '#default_value' => NULL,
          '#required' => TRUE,
        ];
      }
    }

    if ($form_state->hasValue('type')) {
      $form['actions']['back'] = [
        '#type' => 'button',
        '#value' => $this->t('Back'),
        '#ajax' => [
          'event' => 'click',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
      ];
    }

    if (!$base_object) {
      $form['actions']['next'] = [
        '#type' => 'button',
        '#button_type' => 'primary',
        '#value' => $this->t('Next'),
        '#ajax' => [
          'event' => 'click',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
      ];
    }
    else {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#button_type' => 'primary',
        '#value' => $this->t('Create section'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = array_intersect_key($form_state->getValues(), array_flip([
      'type',
      'base_object',
      'title',
    ]));
    if (!empty($values['type']) && !empty($values['base_object'])) {
      $sections = $this->entityTypeManager->getStorage('node')->loadByProperties([
        'type' => 'section',
        'field_base_object' => $values['base_object'][0]['target_id'],
      ]);
      if (count($sections)) {
        $section = reset($sections);
        $form_state->setErrorByName('base_object', $this->t('A section based on @type <em>@label</em> already exists.', [
          '@type' => strtolower($section->field_base_object->entity->type->entity->label()),
          '@label' => $section->field_base_object->entity->label(),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = array_intersect_key($form_state->getValues(), array_flip([
      'type',
      'base_object',
      'title',
    ]));

    $base_object = $this->entityTypeManager->getStorage('base_object')->load($values['base_object'][0]['target_id']);

    $section = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'section',
      'title' => $values['title'],
      'uid' => $this->currentUser()->id(),
      'status' => FALSE,
    ]);
    $section->field_base_object->entity = $base_object;
    $section->save();

    $form_state->setRedirectUrl($section->toUrl());
  }

}
