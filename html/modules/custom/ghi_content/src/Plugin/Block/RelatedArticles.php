<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurationUpdateInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OptionalTitleBlockInterface;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\hpc_common\Traits\EntityHelperTrait;

/**
 * Provides an 'RelatedArticles' block.
 *
 * @Block(
 *  id = "related_articles",
 *  admin_label = @Translation("Related articles"),
 *  category = @Translation("Narrative Content"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *  },
 *  config_forms = {
 *    "articles" = {
 *      "title" = @Translation("Articles"),
 *      "callback" = "articlesForm",
 *      "base_form" = TRUE
 *    },
 *    "display" = {
 *      "title" = @Translation("Display"),
 *      "callback" = "displayForm"
 *    }
 *  }
 * )
 */
class RelatedArticles extends ContentBlockBase implements MultiStepFormBlockInterface, OptionalTitleBlockInterface, ConfigurationUpdateInterface {

  use EntityHelperTrait;

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

    $display = $conf['display']['select'];

    // If articles have been selected, use only those, otherwise use all.
    if (!empty($display['selected'])) {
      $articles = array_intersect_key($articles, array_flip($display['selected']));
    }
    // If an order has been set, use that.
    if (!empty($display['order'])) {
      $articles = array_filter(array_map(function ($id) use ($articles) {
        return $articles[$id] ?? NULL;
      }, $display['order']));
    }

    // Build the render array.
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
      'articles' => [
        'article_select' => [
          'entity_ids' => [],
        ],
      ],
      'display' => [
        'select' => [
          'order' => NULL,
          'selected' => [],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform($is_new = FALSE) {
    return 'articles';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitleSubform() {
    return 'display';
  }

  /**
   * Form callback for the articles configuration form.
   */
  public function articlesForm(array $form, FormStateInterface $form_state) {
    $entity_ids = $this->getDefaultFormValueFromFormState($form_state, ['article_select', 'entity_ids']) ?? [];
    $form['article_select'] = [
      '#type' => 'article_select',
      '#default_value' => $entity_ids,
    ];
    return $form;
  }

  /**
   * Form callback for the display configuration form.
   */
  public function displayForm(array $form, FormStateInterface $form_state) {
    $articles = $this->getArticles();
    $form['select'] = [
      '#type' => 'entity_preview_select',
      '#title' => $this->t('Cards'),
      '#description' => $this->t('This is a preview of the articles that will be shown in this element. You can move the cards around to reorder them. Clicking on a card selects the article for display. If no article is selected, all available articles will be shown.'),
      '#entities' => $articles,
      '#entity_type' => 'node',
      '#view_mode' => 'grid',
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'select',
      ]),
      '#empty' => $this->t('No articles added yet. Go to the "Articles" screen and add some articles first.'),
    ];

    $form['#attached'] = [
      'library' => ['ghi_content/admin.related_articles'],
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
    $conf = $this->getBlockConfig();
    $entity_keys = $conf['articles']['article_select']['entity_ids'] ?? [];
    /** @var \Drupal\ghi_content\Entity\Article[] $articles */
    $articles = self::loadEntitiesByCompositeIds($entity_keys);
    // Articles are now keyed like this: [entity_type_id:entity_id]. Let's
    // re-key them by the entity id.
    $articles = array_reduce($articles, function ($result, $entity) {
      $result[$entity->id()] = $entity;
      return $result;
    }, []);
    // Exclude the current article node if any.
    $node = $this->getPageNode();
    if ($node && $node instanceof Article && array_key_exists($node->id(), $articles)) {
      unset($articles[$node->id()]);
    }
    // And add the section context if available.
    $section = $this->getCurrentBaseEntity();
    if ($section instanceof SectionNodeInterface) {
      foreach ($articles as $article) {
        if ($article->isPartOfSection($section)) {
          $article->setContextNode($section);
        }
      }
    }
    return $articles;
  }

  /**
   * {@inheritdoc}
   */
  public function updateConfiguration() {
    $configuration = &$this->configuration;
    if (!empty($configuration['hpc']['articles']['article_select']['entity_ids'])) {
      return FALSE;
    }
    $entity_ids = &$configuration['hpc']['articles']['article_select']['entity_ids'];
    $order = &$configuration['hpc']['display']['select']['order'];
    $order = [];
    $selected = &$configuration['hpc']['select']['selected'];
    foreach (($selected ?? []) as $entity_id) {
      $entity_ids[] = 'node:' . $entity_id;
    }
    foreach ($configuration['hpc']['select']['order'] ?? [] as $entity_id) {
      if (in_array($entity_id, $selected)) {
        $order[] = $entity_id;
      }
    }
    $configuration['hpc']['display']['label'] = $configuration['hpc']['label'] ?? NULL;
    $configuration['hpc'] = array_intersect_key($configuration['hpc'], array_flip(['articles', 'display']));
    return TRUE;
  }

}
