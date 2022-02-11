<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Component\Utility\Html as UtilityHtml;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;

/**
 * Provides a 'GhoArticle' block.
 *
 * @Block(
 *  id = "gho_article",
 *  admin_label = @Translation("Article: GHO NCMS"),
 *  category = @Translation("Narrative Content"),
 *  remote_source = "gho_ncms",
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *   }
 * )
 */
class GhoArticle extends GhiContentBlockBase {

  /**
   * {@inheritdoc}
   */
  public function getAutomaticBlockTitle() {
    $conf = $this->getBlockConfig();
    if (empty($conf['article_id'])) {
      return;
    }
    $remote_source = $this->getRemoteSource();
    if (!$remote_source) {
      // No available source. Technically, this block is broken now.
      return;
    }

    return $remote_source->getArticleTitle($conf['article_id']);
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $conf = $this->getBlockConfig();
    if (empty($conf['article_id'])) {
      return;
    }

    $remote_source = $this->getRemoteSource();
    if (!$remote_source) {
      // No available source. Technically, this block is broken now.
      return;
    }

    $article = $remote_source->getArticle($conf['article_id']);

    $content = array_map(function ($paragraph) use ($remote_source) {
      return $remote_source->changeRessourceLinks($paragraph->rendered);
    }, $article->content);

    // Add GHO specific theme components.
    $theme_components = [];
    foreach ($article->content as $paragraph) {
      $theme_components[] = 'common_design_subtheme/gho-' . UtilityHtml::getClass($paragraph->type);
    }

    $build = [
      '#type' => 'container',
      '#title' => $article->title,
      'content' => [
        '#type' => 'markup',
        '#markup' => Markup::create(implode('', $content)),
      ],
    ];

    if ($this->moduleHandler->moduleExists('gho_footnotes')) {
      $build['content']['#view_mode'] = 'full';
      $theme_components[] = 'common_design_subtheme/gho-footnotes';
      gho_footnotes_prepare_build($build['content'], $this->getPageNode());
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
      'article_id' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    $remote_source = $this->getRemoteSource();
    if (!$remote_source) {
      // No remote source is set yet, so there is nothing we can do.
      return $form;
    }

    $conf = $this->getBlockConfig();
    $plugin_definitinon = $this->getPluginDefinition();

    $article = !empty($conf['article_id']) ? $remote_source->getArticle($conf['article_id']) : NULL;
    $form['article_id'] = [
      '#type' => 'hidden',
      '#default_value' => $conf['article_id'],
    ];
    $form['article'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Article'),
      '#description' => $this->t('Type the title of an article to see suggestions'),
      '#default_value' => $article ? $article->title . ' (' . $article->id . ')' : NULL,
      '#autocomplete_route_name' => 'ghi_content.remote.autocomplete_article',
      '#autocomplete_route_parameters' => [
        'remote_source' => $plugin_definitinon['remote_source'],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $value_key = ['basic', 'article'];
    if ($form_state->hasValue($value_key)) {
      $article_id = EntityAutocomplete::extractEntityIdFromAutocompleteInput($form_state->getValue($value_key));
      $form_state->setTemporaryValue(['basic', 'article_id'], $article_id);
    }
    parent::blockSubmit($form, $form_state);
  }

}
