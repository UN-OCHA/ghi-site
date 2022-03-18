<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;

/**
 * Provides a 'ArticleCollection' block.
 *
 * @Block(
 *  id = "article_collection",
 *  admin_label = @Translation("Article collection"),
 *  category = @Translation("Narrative Content"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *   }
 * )
 */
class ArticleCollection extends ContentBlockBase implements MultiStepFormBlockInterface {

  const MAX_FEATURE_COUNT = 2;

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
    $display = $this->getBlockConfig()['display'];
    $options = [];

    $articles = $this->getArticles();
    if (empty($articles)) {
      // Nothing to show.
      return;
    }

    if ($display['type'] == 'cards') {
      if ($display['cards']['populate'] == 'manual' && !empty($display['cards']['select'])) {
        if (!empty($display['cards']['select']['selected'])) {
          $articles = array_intersect_key($articles, array_flip($display['cards']['select']['selected']));
        }
        if (!empty($display['cards']['select']['order'])) {
          $articles = array_filter(array_map(function ($id) use ($articles) {
            return $articles[$id] ?? NULL;
          }, $display['cards']['select']['order']));
        }
        $options['featured'] = $display['cards']['select']['featured'] ?? [];
      }
      else {
        $card_limit = $display['cards']['count'] ?? 6;
        $articles = $this->getArticles($card_limit);
      }
    }

    $build = [
      '#theme' => 'article_collection_' . $display['type'],
      '#title' => $this->t('Article collection'),
      '#articles' => $articles,
      '#featured' => $featured,
      '#options' => [
        'columns' => 3,
      ] + $options,
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
          'tag_content_selected' => [],
        ],
        'data' => NULL,
        'sort' => [
          'type' => 'date',
          'order' => 'asc',
        ],
      ],
      'display' => [
        // Can be "cards" or "table".
        'type' => 'cards',
        // Card specific configuration.
        'cards' => [
          'populate' => 'manual',
          'count' => 6,
          'select' => [
            'order' => NULL,
            'selected' => [],
            'featured' => [],
          ],
        ],
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
   * Form callback for the articles form.
   */
  public function articlesForm(array $form, FormStateInterface $form_state) {

    $section = $this->getCurrentBaseEntity();
    $section_tag_ids = array_keys($this->articleManager->getTags($section));

    // Get the available tags, section tags will be first.
    $available_tags = $this->articleManager->loadAvailableTagsForSection($section);
    $node_ids_by_tag = $this->articleManager->getNodeIdsGroupedByTag($section);
    $node_previews = $this->articleManager->getNodePreviews($this->articleManager->loadNodesForSection($section), 'grid');

    // Get the defaults.
    $default_tags = $this->getDefaultFormValueFromFormState($form_state, 'tags') ?? [];

    $form['tags'] = [
      '#type' => 'tag_selection',
      '#title' => $this->t('Tags'),
      '#tags' => $available_tags,
      '#default_value' => $default_tags,
      '#preview_summary' => [
        'ids_by_tag' => $node_ids_by_tag,
        'previews' => $node_previews,
        'labels' => [
          'singular' => $this->t('1 article'),
          'plural' => $this->t('@count articles'),
        ],
      ],
      // Section tags can't be unselected because they define the basic content
      // "universe".
      '#disabled_tags' => $section_tag_ids,
    ];

    $form['#attached'] = [
      'library' => ['ghi_content/admin.article_collection'],
    ];

    return $form;
  }

  /**
   * Form callback for the display form.
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
    $form['type']['table']['#disabled'] = TRUE;
    $form['type']['table']['#attributes']['title'] = $this->t('Not yet supported');

    $form['cards'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $form['cards']['populate'] = [
      '#type' => 'radios',
      '#title' => $this->t('Population method'),
      '#options' => [
        'auto' => $this->t('Automatic'),
        'manual' => $this->t('Manual selection'),
      ],
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'cards',
        'populate',
      ]),
      '#states' => [
        'visible' => [
          ':input[name="display[type]"]' => ['value' => 'cards'],
        ],
      ],
    ];
    $form['cards']['count'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of cards'),
      '#min' => 0,
      '#max' => 9,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'cards',
        'count',
      ]),
      '#states' => [
        'visible' => [
          ':input[name="display[cards][populate]"]' => ['value' => 'auto'],
        ],
      ],
    ];

    $form['cards']['select'] = [
      '#type' => 'entity_preview_select',
      '#title' => $this->t('Cards'),
      '#description' => $this->t('Select the articles that should be shown as cards. If no article is selected, all articles as defined by the constraints in the article selection dialog will be displayed. The article cards can be re-ordered by moving the cards, and up to @count cards can be selected as featured. Featured articles will be visually highlighted in the final display of this element.', [
        '@count' => self::MAX_FEATURE_COUNT,
      ]),
      '#entities' => $this->getArticles(),
      '#entity_type' => 'node',
      '#view_mode' => 'grid',
      '#allow_featured' => self::MAX_FEATURE_COUNT,
      '#limit_field' => Html::getClass(implode('-', array_merge(['edit'], $form['#parents'], [
        'cards',
        'count',
      ]))),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'cards',
        'select',
      ]),
      '#states' => [
        'visible' => [
          ':input[name="display[cards][populate]"]' => ['value' => 'manual'],
        ],
      ],
    ];

    $form['#attached'] = [
      'library' => ['ghi_content/admin.article_collection'],
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
    $section = $this->getCurrentBaseEntity();
    $tag_ids = $this->getApplicabbleTagIds();
    $tag_conjunction = $this->getTagConjunction();
    return $this->articleManager->loadNodesForTags($tag_ids, $section, $tag_conjunction, $limit);
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
