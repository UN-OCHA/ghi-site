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
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    self::prepareAjaxForm($form, $form_state);
    $wrapper_id = self::getWrapperId($form);
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';

    // Define our steps.
    $steps = [
      'year',
      'tags',
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
      '#description' => $this->t('Enter a year for this global section'),
      '#default_value' => $form_state->getValue('year'),
      '#required' => TRUE,
      '#disabled' => $step > 0,
    ];

    $tags = $this->getEntityReferenceFieldItemList('global_section', 'field_tags', $form_state->getValue('tags') ?? []);

    // Add the team selector.
    $form['tags'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Tags'),
      '#description' => $this->t('Select the tags associated with this global section. This controls the content that will be available. Enter multiple tags separated by comma.'),
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
        '#value' => $this->t('Create global section'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = array_intersect_key($form_state->getValues(), array_flip([
      'year',
      'tags',
      'title',
    ]));

    $action = self::getActionFromFormState($form_state);

    if ($action != 'back' && $form_state->get('step') > 0) {
      $properties = [
        'type' => 'global_section',
        'field_year' => $values['year'],
      ];
      $sections = $this->entityTypeManager->getStorage('node')->loadByProperties($properties);
      if (count($sections)) {
        $form_state->setErrorByName('year', $this->t('A global section based on year <em>@year</em> already exists.', [
          '@year' => $values['year'],
        ]));
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
      'title',
    ]));

    // Clear the error messages.
    $this->messenger()->deleteAll();

    // Create and save the section.
    $global_section = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'global_section',
      'title' => $values['title'],
      'uid' => $this->currentUser()->id(),
      'status' => FALSE,
    ]);
    $global_section->field_year = $values['year'];
    $global_section->field_tags = $values['tags'];
    $status = $global_section->save();
    if ($status) {
      $this->messenger()->addStatus($this->t('Created @type for @title', [
        '@type' => $global_section->type->entity->label(),
        '@title' => $global_section->label(),
      ]));
    }

    // Due to the way that the pathauto module works, the alias generation for
    // the subpages is not finished at this point. This is because at the time
    // when each subpage gets created and it's alias build, the section alias
    // itself has not been build, so that token replacements are not fully
    // available. To fix this, we invoke a custom hook that lets the
    // GHI Subpages module react just after a section has been fully build.
    $this->moduleHandler->invokeAll('section_post_create', [$global_section]);

    $form_state->setRedirectUrl($global_section->toUrl());
  }

}
