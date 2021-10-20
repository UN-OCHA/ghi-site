<?php

namespace Drupal\ghi_sections\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
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
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The current user.
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
   * Constructs a SubpagesPages form.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, ModuleHandlerInterface $module_handler, AccountProxyInterface $user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->moduleHandler = $module_handler;
    $this->currentUser = $user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('module_handler'),
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

    // Find out what base objects types can be referenced.
    $fields = $this->entityFieldManager->getFieldDefinitions('node', 'section');
    /** @var \Drupal\field\Entity\FieldConfig $base_object_field_config */
    $base_object_field_config = $fields['field_base_object'];
    $allowed_base_object_types = $base_object_field_config->getSetting('handler_settings')['target_bundles'];

    // Then get the list of available base object types and filter it by the
    // allowed ones.
    $base_object_types = $this->entityTypeManager->getStorage('base_object_type')->loadMultiple();
    $base_object_types = array_filter($base_object_types, function ($type) use ($allowed_base_object_types) {
      return in_array($type->id(), $allowed_base_object_types);
    });
    $base_object_type = $this->getSubmittedBaseObjectType($form_state);

    // And also get the base object in case it has already been submitted.
    $base_object = $this->getSubmittedBaseObject($form_state);

    // See if this needs a year.
    $needs_year = $this->needsYear($form_state);

    // Define our steps.
    $steps = [
      'type',
      'base_object',
      'year',
      'title',
    ];
    // Find out in which step we currently are.
    $step = $form_state->get('step') ?: reset(array_keys($steps));
    $action = self::getActionFromFormState($form_state);

    // Do the step navigation.
    if ($action === 'back' && $step > 0) {
      $step--;
      if (!$needs_year && $step > 1 && $step < 3) {
        $step--;
      }
    }
    elseif ($action == 'next' && $step < count($steps)) {
      $step++;
      if (!$needs_year && $step > 1) {
        $step++;
      }
    }
    $form_state->set('step', $step);

    // Select the base object type.
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Section type'),
      '#description' => $this->t('Select a section type.'),
      '#options' => array_map(function ($type) {
        return $type->label();
      }, $base_object_types),
      '#default_value' => $base_object_type ? $base_object_type->id() : NULL,
      '#disabled' => $step > 0,
    ];

    // Select the base object.
    $form['base_object'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'base_object',
      '#title' => $this->t('Base object'),
      '#description' => $this->t('Select a base object for this section.'),
      '#default_value' => $base_object,
      '#tags' => TRUE,
      '#selection_settings' => [
        'target_bundles' => $base_object_type ? [$base_object_type->id()] : NULL,
      ],
      '#disabled' => $step > 1,
      '#required' => TRUE,
      '#access' => $step > 0 && $base_object_type,
    ];

    // Add the year if appropriate.
    $form['year'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Year'),
      '#description' => $this->t('Enter a year for this section'),
      '#default_value' => $form_state->getValue('year'),
      '#required' => TRUE,
      '#disabled' => $step > 2,
      '#access' => $step > 1 && $base_object && $needs_year,
    ];

    if ($step == array_flip($steps)['title']) {
      // Set a title.
      $form['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title'),
        '#description' => $this->t('Optional: Change the title for this section'),
        '#default_value' => $base_object->label(),
        '#required' => TRUE,
      ];
      if ($needs_year) {
        $form['title']['#default_value'] .= ' ' . $form_state->getValue('year');
      }
    }

    if ($step > 0) {
      $form['actions']['back'] = [
        '#type' => 'button',
        '#value' => $this->t('Back'),
        '#limit_validation_errors' => array_filter([
          $step > 0 ? ['type'] : NULL,
          $step > 1 ? ['base_object'] : NULL,
          $step > 2 ? ['year'] : NULL,
        ]),
        '#ajax' => [
          'event' => 'click',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
      ];
    }

    if ($step < count($steps) - 1) {
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
      'title',
      'year',
    ]));

    $action = self::getActionFromFormState($form_state);
    $base_object = $this->getSubmittedBaseObject($form_state);

    if ($action != 'back' && $form_state->get('step') > 0 && $this->baseObjectComplete($form_state)) {
      $properties = [
        'type' => 'section',
        'field_base_object' => $base_object->id(),
      ];
      if ($this->needsYear($form_state) && !empty($values['year'])) {
        $properties['field_year'] = $values['year'];
      }
      $sections = $this->entityTypeManager->getStorage('node')->loadByProperties($properties);
      if (count($sections)) {
        $section = reset($sections);
        if ($this->needsYear($form_state)) {
          $form_state->setErrorByName('year', $this->t('A section based on @type <em>@label</em> and year <em>@year</em> already exists.', [
            '@type' => strtolower($section->field_base_object->entity->type->entity->label()),
            '@label' => $section->field_base_object->entity->label(),
            '@year' => $values['year'],
          ]));
        }
        else {
          $form_state->setErrorByName('base_object', $this->t('A section based on @type <em>@label</em> already exists.', [
            '@type' => strtolower($section->field_base_object->entity->type->entity->label()),
            '@label' => $section->field_base_object->entity->label(),
          ]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = array_intersect_key($form_state->getValues(), array_flip([
      'title',
      'year',
    ]));

    $base_object_type = $this->getSubmittedBaseObjectType($form_state);
    $base_object = $this->getSubmittedBaseObject($form_state);

    // Create and save the section.
    $section = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'section',
      'title' => $values['title'],
      'uid' => $this->currentUser()->id(),
      'status' => FALSE,
    ]);
    $section->field_base_object->entity = $base_object;
    if ($base_object_type->needsYearForDataRetrieval()) {
      $section->field_year = $values['year'];
    }
    $section->save();

    // Due to the way that the pathauto module works, the alias generation for
    // the subpages is not finished at this point. This is because at the time
    // when each subpage gets created and it's alias build, the section alias
    // itself has not been build, so that token replacements are not fully
    // available. To fix this, we invoke a custom hook that lets the
    // GHI Subpages module react just after a section has been fully build.
    $this->moduleHandler->invokeAll('section_post_create', [$section]);

    $form_state->setRedirectUrl($section->toUrl());
  }

  /**
   * See if the base object is complete.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return bool
   *   TRUE if the base object is complete, FALSE otherwhise.
   */
  private function baseObjectComplete(FormStateInterface $form_state) {
    $base_object_type = $this->getSubmittedBaseObjectType($form_state);
    $base_object = $this->getSubmittedBaseObject($form_state);
    return $base_object && $base_object_type && (!$base_object_type->needsYearForDataRetrieval() || $form_state->getValue('year'));
  }

  /**
   * See if the section to be created needs a year.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return bool
   *   TRUE if the section needs a year, FALSE otherwhise.
   */
  private function needsYear(FormStateInterface $form_state) {
    $base_object_type = $this->getSubmittedBaseObjectType($form_state);
    if (!$base_object_type) {
      return FALSE;
    }
    return $base_object_type->needsYearForDataRetrieval();
  }

  /**
   * Get the submitted base object type.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectTypeInterface
   *   The base object if one has been submitted already.
   */
  private function getSubmittedBaseObjectType(FormStateInterface $form_state) {
    /** @var \Drupal\ghi_base_objects\Entity\BaseObjectTypeInterface $entity*/
    $base_object_type = NULL;
    if ($form_state->hasValue('type')) {
      $base_object_type = $this->entityTypeManager->getStorage('base_object_type')->load($form_state->getValue('type'));
    }
    return $base_object_type;
  }

  /**
   * Get the submitted base object.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface
   *   The base object if one has been submitted already.
   */
  private function getSubmittedBaseObject(FormStateInterface $form_state) {
    /** @var \Drupal\ghi_base_objects\Entity\BaseObjectInterface $entity*/
    $base_object = NULL;
    if ($form_state->hasValue('base_object')) {
      $base_object = $this->entityTypeManager->getStorage('base_object')->load($form_state->getValue('base_object')[0]['target_id']);
    }
    return $base_object;
  }

}
