<?php

namespace Drupal\ghi_sections\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_form_elements\Form\WizardBase;
use Drupal\ghi_sections\Entity\Section;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a wizard form for creating section nodes.
 */
class SectionWizard extends WizardBase {

  /**
   * The section manager.
   *
   * @var \Drupal\ghi_sections\Import\SectionManager
   */
  protected $sectionManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var static $instance */
    $instance = parent::create($container);
    $instance->sectionManager = $container->get('ghi_sections.manager');
    return $instance;
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
  protected function getBundle() {
    return Section::BUNDLE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL) {
    $form = parent::buildForm($form, $form_state, $node);

    $base_object_types = $this->sectionManager->getAvailableBaseObjectTypes();
    if (!$base_object_types) {
      // Bail out if there are no base objects.
      $this->messenger()->addError($this->t('No base objects available to create a section.'));
      return $form;
    }
    $base_object_type = $this->getSubmittedBaseObjectType($form_state);

    // And also get the base object in case it has already been submitted.
    $base_object = $this->getSubmittedBaseObject($form_state);

    // See if this needs a year.
    $needs_year = $this->needsYear($form_state);

    // Get the team options.
    $team_options = $this->getTeamOptions($form_state);
    if (empty($team_options)) {
      // Bail out if there are no teams.
      $this->messenger()->addError($this->t('No teams found. You must import teams before sections can be created.'));
      return $form;
    }

    // Define our steps.
    $steps = array_values(array_filter([
      'type',
      'base_object',
      $needs_year ? 'year' : NULL,
      'tags',
      'team',
      'title',
    ]));

    // Find out in which step we currently are.
    $step = $form_state->get('step') ?: array_key_first($steps);
    $action = self::getActionFromFormState($form_state);

    // Do the step navigation.
    if ($action === 'back' && $step > 0) {
      $step--;
    }
    elseif ($action == 'next' && $step < count($steps)) {
      $step++;
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
      '#disabled' => $needs_year && $step > array_flip($steps)['year'],
      '#access' => $needs_year && $step >= array_flip($steps)['year'],
    ];

    $tag_ids = $this->getSubmittedTagIds($form_state);
    $tags = $this->getEntityReferenceFieldItemList(Section::BUNDLE, 'field_tags', $tag_ids ?? []);

    // Add the team selector.
    $form['tags'] = [
      '#type' => 'entity_autocomplete_active_tags',
      '#title' => $this->t('Tags'),
      '#description' => $this->t('Select the tags associated with this section. This controls the content that will be available. Enter multiple tags separated by comma.'),
      '#target_type' => 'taxonomy_term',
      '#selection_handler' => 'default',
      '#selection_settings' => [
        'target_bundles' => ['tags'],
      ],
      '#autocreate' => [
        'bundle' => 'tags',
        'uid' => $this->currentUser()->id(),
      ],
      '#tags' => TRUE,
      '#default_value' => $tags->referencedEntities(),
      '#required' => TRUE,
      '#disabled' => $step > array_flip($steps)['tags'],
      '#access' => $step >= array_flip($steps)['tags'],
      '#attached' => [
        'library' => ['ghi_form_elements/active_tags'],
      ],
      '#element_validate' => ['ghi_form_elements_entity_autocomplete_active_tags_element_validate'],
      '#maxlength' => 512,
    ];

    // Add the team selector.
    $form['team'] = [
      '#type' => 'select',
      '#title' => $this->t('Team'),
      '#options' => $team_options,
      '#description' => $this->t('Select the team that will be responsible for this section.'),
      '#default_value' => $form_state->getValue('team'),
      '#required' => TRUE,
      '#disabled' => $step > array_flip($steps)['team'],
      '#access' => $step >= array_flip($steps)['team'],
    ];

    // Set a title.
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('Optional: Change the title for this section.'),
      '#default_value' => $base_object ? $base_object->label() : NULL,
      '#required' => TRUE,
      '#access' => $step >= array_flip($steps)['title'],
    ];
    if ($needs_year) {
      $form['title']['#default_value'] .= ' ' . $form_state->getValue('year');
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    if ($step > 0) {
      $form['actions']['back'] = [
        '#type' => 'button',
        '#value' => $this->t('Back'),
        '#limit_validation_errors' => array_filter([
          $step > array_flip($steps)['type'] ? ['type'] : NULL,
          $step > array_flip($steps)['base_object'] ? ['base_object'] : NULL,
          $step > array_flip($steps)['tags'] ? ['tags'] : NULL,
          $needs_year && $step > array_flip($steps)['year'] ? ['year'] : NULL,
          $step > array_flip($steps)['team'] ? ['team'] : NULL,
        ]),
        '#ajax' => [
          'event' => 'click',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $this->ajaxWrapperId,
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
          'wrapper' => $this->ajaxWrapperId,
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
    parent::validateForm($form, $form_state);

    $values = array_intersect_key($form_state->getValues(), array_flip([
      'year',
      'team',
      'title',
    ]));

    $action = self::getActionFromFormState($form_state);
    $base_object = $this->getSubmittedBaseObject($form_state);

    if ($action != 'back' && $form_state->get('step') > 0 && $this->baseObjectComplete($form_state)) {
      $section = $this->sectionManager->loadSectionForBaseObject($base_object, $base_object->needsYear() ? $values['year'] : NULL);
      if ($section) {
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
      'year',
      'tags',
      'team',
      'title',
    ]));

    $base_object = $this->getSubmittedBaseObject($form_state);

    // Since we are using the active tags module now, we need to transform the
    // submitted value for the tags.
    $values['tags'] = $this->getSubmittedTagIds($form_state);

    // Clear the error messages.
    $this->messenger()->deleteAll();

    // Create and save the section.
    $section = $this->sectionManager->createSectionForBaseObject($base_object, $values);
    if ($section) {
      $this->messenger()->addStatus($this->t('Created @type for @title', [
        '@type' => $section->type->entity->label(),
        '@title' => $section->label(),
      ]));
    }

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

  /**
   * Get the submitted tag ids.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return int[]
   *   An array of tag ids.
   */
  private function getSubmittedTagIds(FormStateInterface $form_state) {
    $submitted_tags = $form_state->getValue('tags');
    if (is_array($submitted_tags)) {
      return $submitted_tags;
    }
    return array_map(function ($item) {
      return $item->value;
    }, json_decode($form_state->getValue('tags') ?: '') ?? []);
  }

}
