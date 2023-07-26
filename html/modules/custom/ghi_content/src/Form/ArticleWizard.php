<?php

namespace Drupal\ghi_content\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a wizard form for creating article nodes.
 */
class ArticleWizard extends ContentWizardBase {

  /**
   * The article manager.
   *
   * @var \Drupal\ghi_content\ContentManager\ArticleManager
   */
  protected $articleManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->articleManager = $container->get('ghi_content.manager.article');
    return $instance;
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

    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->entityTypeManager->getStorage('node_type')->load(ArticleManager::ARTICLE_BUNDLE);

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
      '#default_value' => $article ? $article : NULL,
      '#required' => TRUE,
      '#disabled' => $step > array_flip($steps)['article'],
      '#access' => $step >= array_flip($steps)['article'],
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
      '#default_value' => $article ? trim($article->getTitle()) : NULL,
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
    $article = $this->getSubmittedArticle($form_state);
    if ($action != 'back' && $form_state->get('step') == 1 && $article) {
      $node = $this->articleManager->loadNodeForRemoteContent($article);
      if ($node) {
        $form_state->setErrorByName('article', $this->t('An @type for the selected article already exists: <a href="@url">@title</a>.', [
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
      'article',
      'team',
      'title',
    ]));

    // Clear the error messages.
    $this->messenger()->deleteAll();

    $article = $this->getSubmittedArticle($form_state);

    // Create the article.
    $node = $this->articleManager->createNodeFromRemoteArticle($article, $values['title'], $values['team']);
    if ($node) {
      $this->messenger()->addStatus($this->t('Created @type for @title', [
        '@type' => $node->type->entity->label(),
        '@title' => $node->label(),
      ]));
      $form_state->setRedirectUrl($node->toUrl());
    }
    else {
      $this->messenger()->addError($this->t('There was an error processing this form. Please check the logs or contact an administrator'));
    }
  }

  /**
   * Get the submitted article.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteArticleInterface
   *   The article object as retrieved from the remote.
   */
  private function getSubmittedArticle(FormStateInterface $form_state) {
    $source = $this->getSubmittedSource($form_state);
    if (!$source) {
      return NULL;
    }

    $article = $form_state->getValue('article');
    if (empty($article)) {
      return NULL;
    }
    return $source->getArticle($article);
  }

}
