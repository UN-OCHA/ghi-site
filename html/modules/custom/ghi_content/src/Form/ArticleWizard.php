<?php

namespace Drupal\ghi_content\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\RemoteSource\RemoteSourceManager;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a wizard form for creating article nodes.
 */
class ArticleWizard extends FormBase {

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
   * The article manager.
   *
   * @var \Drupal\ghi_content\ContentManager\ArticleManager
   */
  protected $articleManager;

  /**
   * The attachment query.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager
   */
  public $remoteSourceManager;

  /**
   * Constructs a document create form.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, ModuleHandlerInterface $module_handler, AccountProxyInterface $user, ArticleManager $article_manager, RemoteSourceManager $remote_source_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->moduleHandler = $module_handler;
    $this->currentUser = $user;
    $this->articleManager = $article_manager;
    $this->remoteSourceManager = $remote_source_manager;
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
      $container->get('ghi_content.manager.article'),
      $container->get('plugin.manager.remote_source'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_article_wizard';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    self::prepareAjaxForm($form, $form_state);
    $wrapper_id = self::getWrapperId($form);
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';

    $source_options = $this->getSourceOptions();
    if (empty($source_options)) {
      // Bail out if there are no teams.
      $this->messenger()->addError($this->t('No remote sources found. You must create at least one remote source before articles can be created.'));
      return $form;
    }

    // Get the team options.
    $team_options = $this->getTeamOptions($form_state);
    if (empty($team_options)) {
      // Bail out if there are no teams.
      $this->messenger()->addError($this->t('No teams found. You must import teams before articles can be created.'));
      return $form;
    }

    // Define our steps.
    $steps = array_values(array_filter([
      'source',
      'article',
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
    $article = $this->getSubmittedArticle($form_state);

    $form['source'] = [
      '#type' => 'remote_source',
      '#title' => $this->t('Content source'),
      '#description' => $this->t('Select the source of the article.'),
      '#default_value' => $source->getPluginId(),
      '#required' => TRUE,
      '#disabled' => $step > 0,
      '#access' => $step >= 0,
    ];

    // Select the remote article.
    $form['article'] = [
      '#type' => 'remote_article_autocomplete',
      '#title' => $this->t('Article'),
      '#remote_source' => $source ? $source->getPluginId() : NULL,
      '#description' => $this->t('Type the title of an article to see suggestions.'),
      '#default_value' => $article ? [$article] : NULL,
      '#required' => TRUE,
      '#disabled' => $step > array_flip($steps)['article'],
      '#access' => $step >= array_flip($steps)['article'],
    ];

    // Add the team selector.
    $form['team'] = [
      '#type' => 'select',
      '#title' => $this->t('Team'),
      '#options' => $team_options,
      '#description' => $this->t('Select the team that will be responsible for this article.'),
      '#default_value' => $form_state->getValue('team'),
      '#required' => TRUE,
      '#disabled' => $step > array_flip($steps)['team'],
      '#access' => $step >= array_flip($steps)['team'],
    ];

    // Set a title.
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('Optional: Change the title for this article.'),
      '#default_value' => $article ? $article->title : NULL,
      '#required' => TRUE,
      '#access' => $step >= array_flip($steps)['title'],
    ];

    if ($step > 0) {
      $form['actions']['back'] = [
        '#type' => 'button',
        '#value' => $this->t('Back'),
        '#limit_validation_errors' => array_filter([
          $step > array_flip($steps)['source'] ? ['source'] : NULL,
          $step > array_flip($steps)['article'] ? ['article'] : NULL,
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
        '#value' => $this->t('Create article'),
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
    $source = $this->getSubmittedSource($form_state);
    $article = $this->getSubmittedArticle($form_state);
    if ($action != 'back' && $form_state->get('step') == 1 && $article) {
      $node = $this->articleManager->loadNodeBySourceAndId($source, $article);
      if ($node) {
        $form_state->setErrorByName('article', $this->t('An article page for the selected article already exists: <a href="@url">@title</a>.', [
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
      'article',
      'team',
      'title',
    ]));

    // Clear the error messages.
    $this->messenger()->deleteAll();

    // Create and save the article.
    $article = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'article',
      'title' => $values['title'],
      'uid' => $this->currentUser()->id(),
      'status' => FALSE,
    ]);
    $article->field_remote_article = [
      0 => [
        'remote_source' => $values['source'],
        'article_id' => $values['article'][0]['article_id'],
      ],
    ];
    $article->field_team = $values['team'];
    $status = $article->save();
    if ($status) {
      $this->messenger()->addStatus($this->t('Created @type for @title', [
        '@type' => $article->type->entity->label(),
        '@title' => $article->label(),
      ]));
    }

    $form_state->setRedirectUrl($article->toUrl());
  }

  /**
   * Get options for the remote source.
   *
   * @return array|null
   *   The remote source options or NULL.
   */
  private function getSourceOptions() {
    $definitions = $this->remoteSourceManager->getDefinitions();
    if (empty($definitions)) {
      return NULL;
    }
    return array_map(function ($remote_source) {
      return $this->remoteSourceManager->createInstance($remote_source);
    }, array_keys($definitions));
  }

  /**
   * Get the submitted article.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\ghi_content\RemoteSource\RemoteSourceInterface
   *   The remote source of the article.
   */
  private function getSubmittedSource(FormStateInterface $form_state) {
    $remote_source = $form_state->getValue('source');
    if (empty($remote_source)) {
      return NULL;
    }
    $instance = $this->remoteSourceManager->createInstance($remote_source);
    return $instance;
  }

  /**
   * Get the submitted article.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return object
   *   The article object as retrieved from the remote.
   */
  private function getSubmittedArticle(FormStateInterface $form_state) {
    $source = $this->getSubmittedSource($form_state);
    if (!$source) {
      return NULL;
    }
    $articles = $form_state->getValue('article');
    if (empty($articles) || empty($articles[0]['article_id'])) {
      return NULL;
    }
    return $source->getArticle($articles[0]['article_id']);
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
