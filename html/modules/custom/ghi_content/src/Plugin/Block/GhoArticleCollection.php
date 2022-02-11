<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;

/**
 * Provides a 'GhoArticleCollection' block.
 *
 * @Block(
 *  id = "gho_article_collection",
 *  admin_label = @Translation("Article collection: GHO NCMS"),
 *  category = @Translation("Narrative Content"),
 *  remote_source = "gho_ncms",
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *   }
 * )
 */
class GhoArticleCollection extends GhiContentBlockBase implements MultiStepFormBlockInterface {

  /**
   * {@inheritdoc}
   */
  public function getAutomaticBlockTitle() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {

    $articles = $this->getArticles();

    $build = [
      '#type' => 'container',
      '#title' => $this->t('Article collection'),
      'content' => [
        '#theme' => 'item_list',
        '#items' => array_map(function ($article) {
          // @todo Implement actual display logic.
          /** @var \Drupal\node\NodeInterface $article */
          return $article->toLink();
        }, $articles),
      ],
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
      'articles' => [
        'tags' => [
          'tag_ids' => [],
          'tag_op' => 'OR',
        ],
      ],
      'display' => [
        // Can be "cards" or "table".
        'type' => 'cards',
        'card_count' => 6,
        // Can be "manual" or "auto".
        'selection' => 'auto',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSubforms() {
    return [
      'articles' => [
        'title' => $this->t('Articles'),
        'callback' => 'articlesForm',
        'base_form' => TRUE,
      ],
      'display' => [
        'title' => $this->t('Display'),
        'callback' => 'displayForm',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform() {
    $conf = $this->getBlockConfig();
    if (!empty($conf['articles'])) {
      return 'display';
    }
    return 'articles';
  }

  /**
   * {@inheritdoc}
   */
  public function articlesForm(array $form, FormStateInterface $form_state) {

    $section = $this->getCurrentBaseEntity();
    $section_tag_ids = array_keys($this->articleManager->getTags($section));

    // Get the available tags, section tags will be first.
    $available_tags = $this->articleManager->loadAvailableTagsForSection($section);
    $node_ids_by_tag = $this->articleManager->getNodeIdsGroupedByTag($section);

    // Get the defaults.
    $default_tags = $this->getDefaultFormValueFromFormState($form_state, 'tags') ?? [];

    $form['tags'] = [
      '#type' => 'tag_selection',
      '#title' => $this->t('Tags'),
      '#tags' => $available_tags,
      '#default_value' => $default_tags,
      '#preview_summary' => [
        'ids_by_tag' => $node_ids_by_tag,
        'labels' => [
          'singular' => $this->t('1 article'),
          'plural' => $this->t('@count articles'),
        ],
      ],
      // Section tags can't be unselected because they define the basic content
      // "universe".
      '#disabled_tags' => $section_tag_ids,
      '#attached' => [
        'library' => ['ghi_content/admin.article_collection'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function displayForm(array $form, FormStateInterface $form_state) {

    $form['type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Display type'),
      '#options' => [
        'cards' => $this->t('Cards'),
        'table' => $this->t('Table'),
      ],
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'type'),
    ];

    $form['card_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of cards'),
      '#min' => 0,
      '#max' => 9,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'card_count'),
      '#states' => [
        'visible' => [
          ':input[name="display[type]"]' => ['value' => 'cards'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * Get the articles that this block should display.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]|null
   *   An array of entity objects indexed by their ids.
   */
  private function getArticles() {
    $section = $this->getCurrentBaseEntity();
    $tag_ids = $this->getApplicabbleTagIds();
    return $this->articleManager->loadNodesForTags($tag_ids, $section, $this->getTagConjunction());
  }

  /**
   * Get the applicabble tags for this block.
   *
   * @return array
   *   The tags that should be applied for article selection.
   */
  private function getApplicabbleTagIds() {
    $conf = $this->getBlockConfig();
    $tag_ids = array_filter($conf['articles']['tags']['tag_ids'] ?? []);
    return $tag_ids;
  }

  /**
   * Get the tag operation (conjunction).
   *
   * @return string
   *   Either 'AND' or 'OR'.
   */
  private function getTagConjunction() {
    $conf = $this->getBlockConfig();
    return $conf['articles']['tags']['tag_op'] ?? 'AND';
  }

}
