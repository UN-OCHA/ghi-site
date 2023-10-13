<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Interfaces\OptionalTitleBlockInterface;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_sections\Entity\SectionNodeInterface;

/**
 * Provides an 'RelatedArticles' block.
 *
 * @Block(
 *  id = "related_articles",
 *  admin_label = @Translation("Related articles"),
 *  category = @Translation("Narrative Content"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *   }
 * )
 */
class RelatedArticles extends ContentBlockBase implements OptionalTitleBlockInterface {

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    // Don't build this block during solr indexing.
    if (PHP_SAPI == 'cli' || strpos($this->getCurrentUri(), '/batch') === 0) {
      return;
    }

    $conf = $this->getBlockConfig();
    $options = [];

    $articles = $this->getArticles();
    if (empty($articles)) {
      // Nothing to show.
      return [];
    }

    if ($conf['mode'] == 'fixed') {
      if (empty($conf['select']) || empty($conf['select']['selected'])) {
        // Nothing selected.
        return [];
      }
      $articles = array_intersect_key($articles, array_flip($conf['select']['selected']));
      if (!empty($conf['select']['order'])) {
        $articles = array_filter(array_map(function ($id) use ($articles) {
          return $articles[$id] ?? NULL;
        }, $conf['select']['order']));
      }
    }

    $build = [
      '#theme' => 'related_articles_cards',
      '#title' => $this->label(),
      '#articles' => $articles,
      '#options' => $options,
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
      'mode' => 'fixed',
      'count' => 6,
      'select' => [
        'order' => NULL,
        'selected' => [],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {

    $form['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Mode'),
      '#options' => [
        'fixed' => $this->t('Fixed'),
      ],
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'mode'),
      '#access' => FALSE,
    ];
    $form['count'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of cards'),
      '#min' => 0,
      '#max' => 9,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'count'),
    ];

    $form['select'] = [
      '#type' => 'entity_preview_select',
      '#title' => $this->t('Cards'),
      '#description' => $this->t('Select the articles that should be shown. The article cards can be re-ordered by moving the cards.'),
      '#entities' => $this->getArticles(),
      '#entity_type' => 'node',
      '#view_mode' => 'grid',
      '#limit_field' => array_merge($form['#parents'], ['count']),
      '#show_filter' => TRUE,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'select',
      ]),
      '#required' => TRUE,
    ];

    $form['#attached'] = [
      'library' => ['ghi_content/admin.related_articles'],
    ];

    return $form;
  }

  /**
   * Get the articles that this block should display.
   *
   * @param int $limit
   *   An optional limit.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]|null
   *   An array of entity objects indexed by their ids.
   */
  private function getArticles($limit = NULL) {
    /** @var \Drupal\ghi_content\Entity\Article[] $articles */
    $articles = $this->articleManager->loadAllNodes();
    $node = $this->getPageNode();
    if ($node && $node instanceof Article && array_key_exists($node->id(), $articles)) {
      unset($articles[$node->id()]);
    }
    $section = $this->getCurrentBaseEntity();
    if ($section instanceof SectionNodeInterface) {
      foreach ($articles as $article) {
        if ($article->isPartOfSection($section)) {
          $article->setContextNode($section);
        }
      };
    }
    return $articles;
  }

}
