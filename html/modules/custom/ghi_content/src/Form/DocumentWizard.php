<?php

namespace Drupal\ghi_content\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_content\ContentManager\DocumentManager;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a wizard form for creating document nodes.
 */
class DocumentWizard extends ContentWizardBase {

  /**
   * The document manager.
   *
   * @var \Drupal\ghi_content\DocumentManager
   */
  protected $documentManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->documentManager = $container->get('ghi_content.manager.document');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_document_wizard';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    self::prepareAjaxForm($form, $form_state);
    $wrapper_id = self::getWrapperId($form);
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';

    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->entityTypeManager->getStorage('node_type')->load(DocumentManager::DOCUMENT_BUNDLE);

    $source_options = $this->getSourceOptions();
    if (empty($source_options)) {
      // Bail out if there are no teams.
      $this->messenger()->addError($this->t('No remote sources found. You must create at least one remote source before creating an @type.', [
        '@type' => $node_type->label(),
      ]));
      return $form;
    }

    // Get the team options.
    $team_options = $this->getTeamOptions($form_state);
    if (empty($team_options)) {
      // Bail out if there are no teams.
      $this->messenger()->addError($this->t('No teams found. You must import teams before creating an @type.', [
        '@type' => $node_type->label(),
      ]));
      return $form;
    }

    // Define our steps.
    $steps = array_values(array_filter([
      'source',
      'document',
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

    $source = $this->getSubmittedSource($form_state) ?? reset($source_options);
    $document = $this->getSubmittedDocument($form_state);

    $form['source'] = [
      '#type' => 'remote_source',
      '#title' => $this->t('Content source'),
      '#description' => $this->t('Select the source of the document.'),
      '#default_value' => $source->getPluginId(),
      '#required' => TRUE,
      '#disabled' => $step > 0,
      '#access' => $step >= 0,
    ];

    // Select the remote document.
    $form['document'] = [
      '#type' => 'remote_document_autocomplete',
      '#title' => $this->t('Document'),
      '#remote_source' => $source ? $source->getPluginId() : NULL,
      '#description' => $this->t('Type the title of a document to see suggestions.'),
      '#default_value' => $document ? $document : NULL,
      '#required' => TRUE,
      '#disabled' => $step > array_flip($steps)['document'],
      '#access' => $step >= array_flip($steps)['document'],
    ];

    // Add the team selector.
    $form['team'] = [
      '#type' => 'select',
      '#title' => $this->t('Team'),
      '#options' => $team_options,
      '#description' => $this->t('Select the team that will be responsible for this @type.', [
        '@type' => $node_type->label(),
      ]),
      '#default_value' => $form_state->getValue('team'),
      '#required' => TRUE,
      '#disabled' => $step > array_flip($steps)['team'],
      '#access' => $step >= array_flip($steps)['team'],
    ];

    // Set a title.
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('Optional: Change the title for this @type.', [
        '@type' => $node_type->label(),
      ]),
      '#default_value' => $document ? trim($document->getTitle()) : NULL,
      '#required' => TRUE,
      '#size' => 128,
      '#access' => $step >= array_flip($steps)['title'],
    ];

    if ($step > 0) {
      $form['actions']['back'] = [
        '#type' => 'button',
        '#value' => $this->t('Back'),
        '#limit_validation_errors' => array_filter([
          $step > array_flip($steps)['source'] ? ['source'] : NULL,
          $step > array_flip($steps)['document'] ? ['document'] : NULL,
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
        '#value' => $this->t('Create @type', [
          '@type' => $node_type->label(),
        ]),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // We need to prepare the ajax form, because validation is called before
    // form building, and in case of errors doesn't reach the buildForm method.
    self::prepareAjaxForm($form, $form_state);

    $action = self::getActionFromFormState($form_state);
    $document = $this->getSubmittedDocument($form_state);
    if ($action != 'back' && $form_state->get('step') == 1 && $document) {
      $node = $this->documentManager->loadNodeForRemoteDocument($document);
      if ($node) {
        $form_state->setErrorByName('document', $this->t('An @type for the selected document already exists: <a href="@url">@title</a>.', [
          '@type' => $node->type->entity->label(),
          '@url' => $node->toUrl()->toString(),
          '@title' => $node->label(),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = array_intersect_key($form_state->getValues(), array_flip([
      'source',
      'document',
      'team',
      'title',
    ]));

    // Clear the error messages.
    $this->messenger()->deleteAll();

    // Create and save the section.
    $document = $this->entityTypeManager->getStorage('node')->create([
      'type' => DocumentManager::DOCUMENT_BUNDLE,
      'title' => $values['title'],
      'uid' => $this->currentUser()->id(),
      'status' => FALSE,
    ]);
    $document->field_team = $values['team'];
    $status = $document->save();
    if ($status) {
      $this->messenger()->addStatus($this->t('Created @type for @title', [
        '@type' => $document->type->entity->label(),
        '@title' => $document->label(),
      ]));
    }

    $form_state->setRedirectUrl($document->toUrl());
  }

  /**
   * Get the submitted document.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteDocumentInterface
   *   The document object as retrieved from the remote.
   */
  private function getSubmittedDocument(FormStateInterface $form_state) {
    $source = $this->getSubmittedSource($form_state);
    if (!$source) {
      return NULL;
    }

    $document = $form_state->getValue('document');
    if (empty($document)) {
      return NULL;
    }
    return $source->getDocument($document);
  }

}
