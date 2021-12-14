<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;

/**
 * Provides a 'ParagraphGho' block.
 *
 * @Block(
 *  id = "gho_paragraph",
 *  admin_label = @Translation("Paragraph: GHO NCMS"),
 *  category = @Translation("Narrative Content"),
 *  remote_source = "gho_ncms",
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *   }
 * )
 */
class GhoParagraph extends GhiContentBlockBase implements MultiStepFormBlockInterface {

  /**
   * {@inheritdoc}
   */
  public function getAutomaticBlockTitle() {
    $conf = $this->getBlockConfig();
    if (empty($conf['paragraph_id'])) {
      return;
    }
    $remote_source = $this->getRemoteSource();
    if (!$remote_source) {
      // No available source. Technically, this block is broken now.
      return;
    }

    return $remote_source->getArticleTitle($conf['paragraph_id']);
  }

  /**
   * Get the configured paragraph from the remote.
   *
   * @return object|null
   *   A paragraph object as retrieved from the remote source.
   */
  private function getParagraph() {
    $conf = $this->getBlockConfig();
    if (empty($conf['paragraph']['paragraph_id'])) {
      return;
    }
    $remote_source = $this->getRemoteSource();
    if (!$remote_source) {
      // No available source. Technically, this block is broken now.
      return;
    }
    $paragraph = $remote_source->getParagraph($conf['paragraph']['paragraph_id']);
    if (!$paragraph) {
      // No paragraph found. Technically, this block is broken now.
      return;
    }
    $paragraph->rendered = $remote_source->changeRessourceLinks($paragraph->rendered);
    return $paragraph;
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $paragraph = $this->getParagraph();
    if (!$paragraph) {
      return;
    }

    $conf = $this->getBlockConfig();

    // Add GHO specific theme components.
    $theme_components = [];
    $theme_components[] = 'common_design_subtheme/gho-' . Html::getClass($paragraph->type);

    $build = [
      '#type' => 'container',
      '#title' => $conf['paragraph']['title'],
      'content' => [
        '#type' => 'markup',
        '#markup' => Markup::create($paragraph->rendered),
        '#view_mode' => 'full',
      ],
    ];

    if ($this->moduleHandler->moduleExists('gho_footnotes')) {
      // Make sure to add the gho-footnotes component.
      $theme_components[] = 'common_design_subtheme/gho-footnotes';
    }

    $build['content']['#attached'] = [
      'library' => $theme_components,
    ];

    return $build;
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'article_select' => [
        'article_id' => NULL,
      ],
      'paragraph' => [
        'paragraph_id' => NULL,
        'title' => NULL,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSubforms() {
    return [
      'article_select' => [
        'title' => $this->t('Article selection'),
        'callback' => 'articleSelectForm',
        'base_form' => TRUE,
      ],
      'paragraph' => [
        'title' => $this->t('Paragraph'),
        'callback' => 'paragraphForm',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform() {
    $conf = $this->getBlockConfig();
    if (!empty($conf['paragraph']['paragraph_id'])) {
      return 'paragraph';
    }
    return 'article_select';
  }

  /**
   * {@inheritdoc}
   */
  public function canShowSubform($form, FormStateInterface $form_state, $subform_key) {
    $conf = $this->getBlockConfig();
    if ($subform_key == 'paragraph') {
      return (array_key_exists('article_id', $conf['article_select']) && !empty($conf['article_select']['article_id'])) || $form_state->hasValue(['article_select']['article_id']);
    }
    return TRUE;
  }

  /**
   * Select article form.
   */
  public function articleSelectForm(array $form, FormStateInterface $form_state) {
    $remote_source = $this->getRemoteSource();
    if (!$remote_source) {
      // No remote source is set yet, so there is nothing we can do.
      return $form;
    }

    $conf = $this->getBlockConfig();
    $plugin_definitinon = $this->getPluginDefinition();
    $wrapper_id = Html::getId('form-wrapper-ghi-block-config');

    $article = !empty($conf['article_select']['article_id']) ? $remote_source->getArticle($conf['article_select']['article_id']) : NULL;

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
        'remote_source' => $plugin_definitinon['remote_source'],
      ],
    ];

    $form['select_article'] = [
      '#type' => 'submit',
      '#value' => $this->t('Select this article'),
      '#element_submit' => [get_class($this) . '::articleSelectSubmit'],
      '#ajax' => [
        'callback' => [$this, 'navigateFormStep'],
        'wrapper' => $wrapper_id,
        'effect' => 'fade',
        'method' => 'replace',
        'parents' => ['settings', 'container'],
      ],
      '#next_step' => 'paragraph',
    ];

    return $form;
  }

  /**
   * Submit callback for the "Select article" button.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response.
   */
  public static function articleSelectSubmit(array $form, FormStateInterface $form_state) {
    $value_key = ['article_select', 'article'];
    if ($form_state->hasValue($value_key)) {
      $article_id = EntityAutocomplete::extractEntityIdFromAutocompleteInput($form_state->getValue($value_key));
      if ($article_id) {
        $form_state->setValue(['article_select', 'article_id'], $article_id);
      }
    }
    return self::ajaxMultiStepSubmit($form, $form_state);
  }

  /**
   * Paragraph config form.
   */
  public function paragraphForm(array $form, FormStateInterface $form_state) {
    $conf = $this->getBlockConfig();
    $article = !empty($conf['article_select']['article_id']) ? $this->getRemoteSource()->getArticle($conf['article_select']['article_id']) : NULL;
    $paragraph = $this->getParagraph();
    $form['article_summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Selected article'),
      '#markup' => $article->title,
    ];

    $options = [];
    foreach ($article->content as $item) {
      $options[$item->id] = Unicode::truncate(strip_tags($item->rendered), 180, FALSE, TRUE);
    }
    $form['paragraph_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Paragraph'),
      '#description' => $this->t('Select a paragraph from the article.'),
      '#options' => $options,
      '#default_value' => $paragraph ? $paragraph->id : NULL,
    ];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('Give this paragraph an optional title.'),
      '#default_value' => !empty($conf['paragraph']['title']) ? $conf['paragraph']['title'] : NULL,
    ];

    return $form;
  }

}
