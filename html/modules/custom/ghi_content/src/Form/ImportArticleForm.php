<?php

namespace Drupal\ghi_content\Form;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ghi_content\Import\ImportManager;
use Drupal\ghi_content\RemoteSource\RemoteSourceManager;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an edit form for remote sources processors.
 */
class ImportArticleForm extends FormBase {

  use AjaxElementTrait;

  /**
   * The remote source plugin to edit.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager
   */
  protected $remoteSourceManager;

  /**
   * The import manager.
   *
   * @var \Drupal\ghi_content\Import\ImportManager
   */
  protected $importManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The node object.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * Constructs a section create form.
   */
  public function __construct(RemoteSourceManager $remote_source_manager, ImportManager $import_manager, AccountProxyInterface $user, TimeInterface $time) {
    $this->remoteSourceManager = $remote_source_manager;
    $this->importManager = $import_manager;
    $this->currentUser = $user;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.remote_source'),
      $container->get('ghi_content.import'),
      $container->get('current_user'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'import_article_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    self::prepareAjaxForm($form, $form_state);
    $wrapper_id = self::getWrapperId($form);
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';

    $this->node = $node;

    // Define our steps.
    $steps = array_values(array_filter([
      'remote_source',
      'article',
      'paragraphs',
      'confirm',
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

    $remote_sources = $this->remoteSourceManager->getDefinitions();
    $disabled = count($remote_sources) <= 1;

    $remote_source = $form_state->hasValue('remote_source') ? $form_state->getValue('remote_source') : array_key_first($remote_sources);

    $form['remote_source'] = [
      '#type' => 'select',
      '#title' => $this->t('Content source'),
      '#description' => $this->t('Select the source of the article.') . ($disabled ? '<br />' . $this->t('<em>Note:</em> This option is deactivated because there is only a single content source available: @content_source', [
        '@content_source' => $remote_sources[array_key_first($remote_sources)]['label'],
      ]) : ''),
      '#options' => array_map(function ($item) {
        return $item['label'];
      }, $remote_sources),
      '#default_value' => $remote_source ? $remote_source : array_key_first($remote_sources),
      '#disabled' => $disabled || $step > 0,
    ];

    $article = $this->getSubmittedArticle($form_state);

    $form['article_id'] = [
      '#type' => 'hidden',
      '#default_value' => $article ? $article->id : NULL,
    ];
    $form['article'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Article'),
      '#description' => $this->t('Type the title of an article to see suggestions.'),
      '#default_value' => $article ? $article->title . ' (' . $article->id . ')' : NULL,
      '#autocomplete_route_name' => 'ghi_content.remote.autocomplete_article',
      '#autocomplete_route_parameters' => [
        'remote_source' => $remote_source,
      ],
      '#access' => $step > 0 && $form_state->hasValue('remote_source'),
      '#disabled' => $step > 1,
    ];

    $options = [];
    if ($article) {
      foreach ($article->content as $item) {
        $options[$item->id] = Unicode::truncate(strip_tags($item->rendered), 180, FALSE, TRUE);
      }
    }
    $paragraph_ids = $form_state->getValue('paragraphs');
    $form['paragraphs'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Paragraphs'),
      '#description' => $this->t('Select the paragraphs from the article.'),
      '#options' => $options,
      '#multiple' => TRUE,
      '#default_value' => !empty($paragraph_ids) ? $paragraph_ids : array_keys($options),
      '#access' => $step > 1 && $article,
      '#disabled' => $step > 2,
    ];

    $form['confirm'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Confirm'),
      '#description' => $this->t('Please review the settings above and confirm this is all ok.'),
      '#default_value' => FALSE,
      '#access' => $step > 2 && $form_state->hasValue('paragraphs'),
      '#disabled' => $step > 3,
    ];
    $form['replace'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Replace existing elements'),
      '#description' => $this->t('Check this if the selected paragraphs should <strong>replace</strong> the existing elements on the page.'),
      '#default_value' => FALSE,
      '#access' => $step > 2 && $form_state->hasValue('paragraphs'),
      '#disabled' => $step > 3,
    ];

    if ($step > 0) {
      $form['actions']['back'] = [
        '#type' => 'button',
        '#value' => $this->t('Back'),
        '#limit_validation_errors' => array_filter([
          $step > array_flip($steps)['remote_source'] ? ['remote_source'] : NULL,
          $step > array_flip($steps)['article'] ? ['article'] : NULL,
          $step > array_flip($steps)['paragraphs'] ? ['paragraphs'] : NULL,
          $step > array_flip($steps)['confirm'] ? ['confirm', 'replace'] : NULL,
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
        '#value' => $this->t('Import article'),
        '#states' => [
          'disabled' => [
            'input[name="confirm"]' => ['checked' => FALSE],
          ],
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $article = $this->getSubmittedArticle($form_state);
    $paragraph_ids = $form_state->getValue('paragraphs');
    $replace = $form_state->getValue('replace');

    $this->importManager->importParagraphs($this->node, $article, $paragraph_ids, NULL, TRUE, $replace);
    $this->node->setNewRevision(TRUE);
    $this->node->revision_log = $this->t('Imported page elements');
    $this->node->setRevisionCreationTime($this->time->getRequestTime());
    $this->node->setRevisionUserId($this->currentUser->id());
    $this->node->save();

    $form_state->setRebuild();
  }

  /**
   * Get the submitted remote source.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\ghi_content\RemoteSource\RemoteSourceInterface
   *   A remote source instance if one has been submitted already.
   */
  private function getSubmittedRemoteSource(FormStateInterface $form_state) {
    /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceInterface $remote_source*/
    $remote_source = NULL;
    if ($form_state->hasValue('remote_source')) {
      $remote_source = $this->remoteSourceManager->createInstance($form_state->getValue('remote_source'));
    }
    return $remote_source;
  }

  /**
   * Get the submitted article.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return object
   *   An article object if submitted already.
   */
  private function getSubmittedArticle(FormStateInterface $form_state) {
    $submitted_article = $form_state->hasValue('article') ? $form_state->getValue('article') : NULL;
    $article_id = $form_state->hasValue('article_id') ? $form_state->getValue('article_id') : NULL;
    $article = $form_state->get('article') ?? NULL;
    if ($article) {
      return $article;
    }
    if ($submitted_article && !$article_id) {
      $article_id = EntityAutocomplete::extractEntityIdFromAutocompleteInput($submitted_article);
    }
    if ($article_id) {
      $remote_source_instance = $this->getSubmittedRemoteSource($form_state);
      $article = $remote_source_instance->getArticle($article_id);
      $form_state->set('article', $article);
    }
    return $article;
  }

}
