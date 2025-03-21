<?php

namespace Drupal\ghi_subpages_custom\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Drupal\ghi_sections\Entity\Section;
use Drupal\ghi_subpages_custom\CustomSubpageManager;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a wizard form for creating custom subpage nodes.
 */
class CustomSubpageWizard extends FormBase {

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
   * The custom subpage manager.
   *
   * @var \Drupal\ghi_subpages_custom\CustomSubpageManager
   */
  protected $customSubpageManager;

  /**
   * Constructs a custom subpage create form.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, ModuleHandlerInterface $module_handler, AccountProxyInterface $user, CustomSubpageManager $custom_subpage_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->moduleHandler = $module_handler;
    $this->currentUser = $user;
    $this->customSubpageManager = $custom_subpage_manager;
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
      $container->get('ghi_subpages_custom.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_subpages_custom_wizard';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL) {
    self::prepareAjaxForm($form, $form_state);
    $wrapper_id = self::getWrapperId($form);
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';

    // Find out what base objects types can be referenced.
    $fields = $this->entityFieldManager->getFieldDefinitions('node', CustomSubpageManager::BUNDLE);
    /** @var \Drupal\field\Entity\FieldConfig $base_object_field_config */
    $section_field_config = $fields['field_entity_reference'];
    $allowed_section_types = $section_field_config->getSetting('handler_settings')['target_bundles'];

    // Then get the list of available base object types and filter it by the
    // allowed ones.
    $section_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $section_types = array_filter($section_types, function ($type) use ($allowed_section_types) {
      return in_array($type->id(), $allowed_section_types);
    });
    $section_type = $this->getSubmittedSectionType($form_state);

    // And also get the base object in case it has already been submitted.
    $section = $this->getSubmittedSection($form_state);

    // Get the team options.
    $team_options = $this->getTeamOptions($form_state);
    if (empty($team_options)) {
      // Bail out if there are no teams.
      $this->messenger()->addError($this->t('No teams found. You must import teams before sections can be created.'));
      return $form;
    }
    $team_options = ['inherit' => $this->t('Inherit from section')] + $team_options;

    // Define our steps.
    $steps = array_values(array_filter([
      'type',
      'section',
      'title',
      'team',
    ]));

    // Find out in which step we currently are.
    $step = $form_state->get('step') ?: array_key_first($steps);
    $first_step = array_key_first($steps);
    $action = self::getActionFromFormState($form_state);

    if (in_array('section', $allowed_section_types) && $section_id = $this->getRequest()->query->get('section')) {
      $_section = $this->entityTypeManager->getStorage('node')->load($section_id);
      if ($_section instanceof Section) {
        $section_type = $_section->type->entity;
        $section = $_section;
        $form_state->setValue('type', $section_type);
        $form_state->setValue('section', $section);
        $step = $step <= array_flip($steps)['title'] ? array_flip($steps)['title'] : $step;
        $first_step = array_flip($steps)['title'];
      }
    }

    // Do the step navigation.
    if ($action === 'back' && $step > $first_step) {
      $step--;
    }
    elseif ($action == 'next' && $step < count($steps)) {
      $step++;
    }
    $form_state->set('step', $step);

    // Select the section type.
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Section type'),
      '#description' => $this->t('Select a section type.'),
      '#options' => array_map(function ($type) {
        return $type->label();
      }, $section_types),
      '#default_value' => $section_type ? $section_type->id() : NULL,
      '#disabled' => $step > 0,
      '#required' => TRUE,
    ];

    // Select the base object.
    $form['section'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#title' => $this->t('Section'),
      '#description' => $this->t('Select the section to which the custom subpage will be added.'),
      '#default_value' => $section,
      '#tags' => TRUE,
      '#selection_handler' => 'default',
      '#selection_settings' => [
        'target_bundles' => $section_type ? [$section_type->id()] : NULL,
      ],
      '#disabled' => $step > 1,
      '#required' => TRUE,
      '#access' => $step > 0 && $section_type,
    ];

    // Set a title.
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('Set the title for this custom subpage'),
      '#default_value' => NULL,
      '#required' => TRUE,
      '#access' => $step >= array_flip($steps)['title'],
    ];

    // Add the team selector.
    $form['team'] = [
      '#type' => 'select',
      '#title' => $this->t('Team'),
      '#options' => $team_options,
      '#description' => $this->t('Select the team that will be responsible for this page. Leave empty to inherit the team from the section.'),
      '#default_value' => $form_state->getValue('team') ?? 'inherit',
      '#disabled' => $step > array_flip($steps)['team'],
      '#access' => $step >= array_flip($steps)['team'],
    ];

    if ($step > $first_step) {
      $form['back'] = [
        '#type' => 'button',
        '#value' => $this->t('Back'),
        '#limit_validation_errors' => array_filter([
          $step > array_flip($steps)['type'] ? ['type'] : NULL,
          $step > array_flip($steps)['section'] ? ['section'] : NULL,
          $step > array_flip($steps)['team'] ? ['team'] : NULL,
        ]),
        '#ajax' => [
          'event' => 'click',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
      ];
    }

    if ($step < count($steps) - 1) {
      $form['next'] = [
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
      $form['submit'] = [
        '#type' => 'submit',
        '#button_type' => 'primary',
        '#value' => $this->t('Create custom subpage'),
        '#validate' => [
          '::validateEntity',
        ],
      ];
    }

    return $form;
  }

  /**
   * Custom callback for entity validation.
   */
  public function validateEntity(array &$form, FormStateInterface $form_state) {
    $subpage = $this->createSubpageFromValues($form_state);
    $violations = $subpage->validate();
    if ($violations->count()) {
      foreach ($violations as $violation) {
        $form_state->setError($form, $violation->getMessage());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Clear the error messages.
    $this->messenger()->deleteAll();

    $subpage = $this->createSubpageFromValues($form_state);
    $status = $subpage->save();
    if ($status) {
      $this->messenger()->addStatus($this->t('Created @type for @title', [
        '@type' => $subpage->type->entity->label(),
        '@title' => $subpage->label(),
      ]));
    }
    $form_state->setRedirectUrl($subpage->toUrl());
  }

  /**
   * Create a subpage node from the submitted form values.
   *
   * @return \Drupal\ghi_subpages_custom\Entity\CustomSubpage
   *   The created custom subpage object, not saved yet.
   */
  private function createSubpageFromValues(FormStateInterface $form_state) {
    $values = array_intersect_key($form_state->getValues(), array_flip([
      'year',
      'team',
      'title',
    ]));
    // Create and save the section.
    $subpage = $this->entityTypeManager->getStorage('node')->create([
      'type' => CustomSubpageManager::BUNDLE,
      'title' => $values['title'],
      'uid' => $this->currentUser()->id(),
      'status' => FALSE,
      'field_hero_image' => [
        'source' => 'inherit',
      ],
    ]);
    $section = $this->getSubmittedSection($form_state);
    $subpage->field_entity_reference->entity = $section;
    if (!empty($values['team']) && $values['team'] != 'inherit') {
      $subpage->field_team = $values['team'];
    }
    return $subpage;
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
  private function getSubmittedSectionType(FormStateInterface $form_state) {
    /** @var \Drupal\node\Entity\Node $entity*/
    $section_type = NULL;
    if ($form_state->hasValue('type')) {
      $section_type = $this->entityTypeManager->getStorage('node_type')->load($form_state->getValue('type'));
    }
    return $section_type;
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
  private function getSubmittedSection(FormStateInterface $form_state) {
    /** @var \Drupal\node\Entity\Node $entity*/
    $section = NULL;
    if ($form_state->hasValue('section')) {
      $section = $this->entityTypeManager->getStorage('node')->load($form_state->getValue('section')[0]['target_id']);
    }
    return $section;
  }

  /**
   * Retrieve the team options for the team select field.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   An array of team names, keyed by tid.
   */
  private function getTeamOptions(FormStateInterface $form_state) {
    // @todo Ideally, this should fetch teams that have access to the base
    // object, but for now we fetch all teams.
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('team');
    if (empty($terms)) {
      return [];
    }
    $options = [];
    foreach ($terms as $term) {
      $options[$term->tid] = $term->name;
    }
    return $options;
  }

}
