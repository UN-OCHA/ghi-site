<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OptionalTitleBlockInterface;
use Drupal\ghi_sections\SectionTrait;

/**
 * Provides an 'ArticleCollection' block.
 *
 * @Block(
 *  id = "article_collection",
 *  admin_label = @Translation("Article collection"),
 *  category = @Translation("Narrative Content"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node"), required = FALSE),
 *   }
 * )
 */
class ArticleCollection extends ContentBlockBase implements MultiStepFormBlockInterface, OptionalTitleBlockInterface {

  use SectionTrait;

  const MAX_FEATURE_COUNT = 2;
  const CARD_LIMIT = 9;
  const CARD_COUNT_DEFAULT = 6;

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
      if ($display['cards']['populate'] == 'manual') {
        $card_select = $display['cards']['select'];

        // First order the articles.
        if (!empty($card_select['order'])) {
          $order = array_filter($card_select['order'], function ($id) use ($articles) {
            return array_key_exists($id, $articles);
          });
          $order = array_map(function ($id) {
            return (int) $id;
          }, $order);
          $articles = array_map(function ($id) use ($articles) {
            return $articles[$id];
          }, array_combine($order, $order));
        }

        // Then get the featured ones into the options.
        $options['featured'] = $card_select['featured'] ?? [];

        // Then filter for only the selected ones if there are any.
        if (!empty($card_select['selected'])) {
          $articles = array_intersect_key($articles, array_flip($card_select['selected']));
        }
        elseif (count($articles) > self::CARD_LIMIT) {
          $articles = array_slice($articles, 0, self::CARD_LIMIT, TRUE);
        }
      }
      else {
        // Otherwhise just take the first X articles.
        $card_limit = $display['cards']['count'] ?? self::CARD_LIMIT;
        $card_limit = $card_limit <= self::CARD_LIMIT ? $card_limit : self::CARD_LIMIT;
        $articles = array_slice($articles, 0, $card_limit, TRUE);
      }
    }

    if (empty($articles)) {
      // Check again if we have something to show.
      return;
    }

    $build = [
      '#theme' => 'article_collection_' . $display['type'],
      '#title' => $this->t('Article collection'),
      '#articles' => $articles,
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
          'count' => self::CARD_COUNT_DEFAULT,
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
  public function getDefaultSubform($is_new = FALSE) {
    $conf = $this->getBlockConfig();
    if (!empty($conf['articles']) && !$is_new) {
      return 'display';
    }
    return 'articles';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitleSubform() {
    return 'display';
  }

  /**
   * {@inheritdoc}
   */
  public function canShowSubform($form, FormStateInterface $form_state, $subform_key) {
    if ($subform_key == 'articles') {
      return TRUE;
    }
    $conf = $this->getBlockConfig();
    return !empty($conf['articles']);
  }

  /**
   * Form callback for the articles form.
   */
  public function articlesForm(array $form, FormStateInterface $form_state) {

    $section = $this->getCurrentBaseEntity();
    $section_tags = $section ? $this->articleManager->getTags($section) : [];
    $section_tag_ids = array_keys($section_tags);

    if ($section && $this->isSectionNode($section)) {
      // Get the available tags, section tags will be first.
      $available_nodes = $this->articleManager->loadNodesForSection($section);
    }
    else {
      // This is a global section or a global landing page. Get all available
      // tags and nodes.
      $available_nodes = $this->articleManager->loadAllNodes();
    }

    $available_tags = $section_tags + $this->articleManager->getAvailableTags($available_nodes);
    $node_ids_by_tag = $this->articleManager->getNodeIdsGroupedByTag($available_nodes);
    $node_previews = $this->articleManager->getNodePreviews($available_nodes, 'grid');

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
      '#max' => self::CARD_LIMIT,
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
      '#description' => $this->t('Up to @max_select articles can be selected to display as cards by clicking on them. If no article is selected, the first @max_select articles as defined by the constraints in the article selection dialog will be displayed. The article cards can be re-ordered by moving the cards, and up to @max_feature cards can be selected as featured. Featured articles will be visually highlighted in the final display of this element.', [
        '@max_select' => self::CARD_LIMIT,
        '@max_feature' => self::MAX_FEATURE_COUNT,
      ]),
      '#entities' => $this->getArticles(),
      '#entity_type' => 'node',
      '#view_mode' => 'grid',
      '#allow_selected' => self::CARD_LIMIT,
      '#allow_featured' => self::MAX_FEATURE_COUNT,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'cards',
        'select',
      ]),
      '#states' => [
        'visible' => [
          ':input[name="display[cards][populate]"]' => ['value' => 'manual'],
        ],
        'required' => [
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
    $tag_ids = $this->getApplicableTagIds();
    $tag_conjunction = $this->getTagConjunction();
    if ($section || $tag_ids) {
      return $this->articleManager->loadNodesForTags($tag_ids, $section, $tag_conjunction, $limit);
    }
    return $this->articleManager->loadAllNodes($limit);
  }

  /**
   * Get the applicabble tags for this block.
   *
   * @return array
   *   The tags that should be applied for article selection.
   */
  private function getApplicableTagIds() {
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
