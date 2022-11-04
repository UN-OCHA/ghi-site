<?php

namespace Drupal\ghi_sections\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

/**
 * Provides a wizard form for creating global section nodes.
 */
class GlobalSectionWizard extends WizardBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_global_sections_wizard';
  }

  /**
   * {@inheritdoc}
   */
  protected function getBundle() {
    return 'global_section';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $form = parent::buildForm($form, $form_state, $node);

    // Get the team options.
    $team_options = $this->getTeamOptions($form_state);
    if (empty($team_options)) {
      // Bail out if there are no teams.
      $this->messenger()->addError($this->t('No teams found. You must import teams before sections can be created.'));
      return $form;
    }

    // Define our steps.
    $steps = [
      'year',
      'tags',
      'team',
      'title',
    ];
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

    // Add the year.
    $form['year'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Year'),
      '#description' => $this->getFieldHelp('field_year'),
      '#default_value' => $form_state->getValue('year'),
      '#required' => TRUE,
      '#disabled' => $step > 0,
    ];

    $tags = $this->getEntityReferenceFieldItemList($this->getBundle(), 'field_tags', $form_state->getValue('tags') ?? []);

    // Add the team selector.
    $form['tags'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Tags'),
      '#description' => $this->getFieldHelp('field_tags'),
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
    ];

    // Add the team selector.
    $form['team'] = [
      '#type' => 'select',
      '#title' => $this->t('Team'),
      '#options' => $team_options,
      '#description' => $this->getFieldHelp('field_team'),
      '#default_value' => $form_state->getValue('team'),
      '#required' => TRUE,
      '#disabled' => $step > array_flip($steps)['team'],
      '#access' => $step >= array_flip($steps)['team'],
    ];

    if ($step == array_flip($steps)['title']) {
      // Set a title.
      $form['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title'),
        '#description' => $this->t('Set a title for this global section'),
        '#required' => TRUE,
      ];
    }

    if ($step > 0) {
      $form['actions']['back'] = [
        '#type' => 'button',
        '#value' => $this->t('Back'),
        '#limit_validation_errors' => array_filter([
          $step > 0 ? ['year'] : NULL,
          $step > array_flip($steps)['tags'] ? ['tags'] : NULL,
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
        '#value' => $this->t('Create @type', [
          '@type' => $this->entityTypeManager->getStorage('node_type')->load($this->getBundle())->label(),
        ]),
      ];
    }

    return $form;
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

    // Clear the error messages.
    $this->messenger()->deleteAll();

    // Create and save the section.
    $global_section = $this->entityTypeManager->getStorage('node')->create([
      'type' => $this->getBundle(),
      'title' => $values['title'],
      'uid' => $this->currentUser()->id(),
      'status' => FALSE,
    ]);
    $global_section->field_year = $values['year'];
    $global_section->field_tags = $values['tags'];
    $global_section->field_team = $values['team'];
    $status = $global_section->save();
    if ($status) {
      $this->messenger()->addStatus($this->t('Created @type for @title', [
        '@type' => $global_section->type->entity->label(),
        '@title' => $global_section->label(),
      ]));
    }

    $form_state->setRedirectUrl($global_section->toUrl());
  }

}
