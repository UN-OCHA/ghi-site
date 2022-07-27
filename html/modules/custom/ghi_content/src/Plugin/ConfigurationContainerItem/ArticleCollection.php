<?php

namespace Drupal\ghi_content\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemCustomActionsInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;
use Drupal\ghi_sections\SectionTrait;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an article collection item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "article_collection",
 *   label = @Translation("Article collection"),
 *   description = @Translation("This item allows the selection of articles based on their tags."),
 * )
 */
class ArticleCollection extends ConfigurationContainerItemPluginBase implements ConfigurationContainerItemCustomActionsInterface {

  use SectionTrait;

  const MAX_FEATURE_COUNT = 2;
  const CARD_LIMIT = 9;
  const CARD_COUNT_DEFAULT = 6;
  const DISPLAY_TYPE_CARDS = 'cards';
  const DISPLAY_TYPE_TABLE = 'table';
  const DISPLAY_CARD_POPULATE_AUTO = 'auto';
  const DISPLAY_CARD_POPULATE_MANUAL = 'manual';

  /**
   * The article manager.
   *
   * @var \Drupal\ghi_content\ContentManager\ArticleManager
   */
  protected $articleManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->articleManager = $container->get('ghi_content.manager.article');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomActions() {
    return [
      'article_selection_form' => $this->t('Articles'),
      'display_form' => $this->t('Display'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);
    return $element;
  }

  /**
   * Build the article selection subform.
   */
  public function articleSelectionForm($element, FormStateInterface $form_state) {
    $section = $this->getContextValue('section');
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
    $default_tags = $this->getSubmittedValue($element, $form_state, 'tags') ?? ($this->config['article_selection_form']['tags'] ?? []);

    $element['tags'] = [
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
    return $element;
  }

  /**
   * Build the display subform.
   */
  public function displayForm($element, FormStateInterface $form_state) {
    $element['type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Display type'),
      '#options' => $this->getDisplayTypeOptions(),
      '#default_value' => $this->getSubmittedValue($element, $form_state, 'type') ?? ($this->config['display_form']['type'] ?? self::DISPLAY_TYPE_CARDS),
    ];
    $element['type']['table']['#disabled'] = TRUE;
    $element['type']['table']['#attributes']['title'] = $this->t('Not yet supported');

    $type_selector = FormElementHelper::getStateSelector($element, ['type']);
    $element['cards'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $element['cards']['populate'] = [
      '#type' => 'radios',
      '#title' => $this->t('Population method'),
      '#options' => $this->getCardPopulateOptions(),
      '#default_value' => $this->getSubmittedValue($element, $form_state, [
        'cards',
        'populate',
      ]) ?? ($this->config['display_form']['cards']['populate'] ?? self::DISPLAY_CARD_POPULATE_AUTO),
      '#states' => [
        'visible' => [
          ':input[name="' . $type_selector . '"]' => ['value' => self::DISPLAY_TYPE_CARDS],
        ],
      ],
    ];
    $card_populate_selector = FormElementHelper::getStateSelector($element, [
      'cards',
      'populate',
    ]);
    $element['cards']['count'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of cards'),
      '#min' => 0,
      '#max' => self::CARD_LIMIT,
      '#default_value' => $this->getSubmittedValue($element, $form_state, [
        'cards',
        'count',
      ]) ?? ($this->config['display_form']['cards']['count'] ?? self::CARD_COUNT_DEFAULT),
      '#states' => [
        'visible' => [
          ':input[name="' . $card_populate_selector . '"]' => ['value' => self::DISPLAY_CARD_POPULATE_AUTO],
        ],
      ],
    ];

    $element['cards']['select'] = [
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
      '#default_value' => $this->getSubmittedValue($element, $form_state, [
        'cards',
        'select',
      ]) ?? ($this->config['display_form']['cards']['select'] ?? []),
      '#states' => [
        'visible' => [
          ':input[name="' . $card_populate_selector . '"]' => ['value' => self::DISPLAY_CARD_POPULATE_MANUAL],
        ],
        'required' => [
          ':input[name="' . $card_populate_selector . '"]' => ['value' => self::DISPLAY_CARD_POPULATE_MANUAL],
        ],
      ],
    ];

    $element['#attached'] = [
      'library' => ['ghi_content/admin.article_collection'],
    ];
    return $element;
  }

  /**
   * Get the display type options.
   *
   * @return array
   *   Array of display type options.
   */
  private function getDisplayTypeOptions() {
    return [
      self::DISPLAY_TYPE_CARDS => $this->t('Cards'),
      self::DISPLAY_TYPE_TABLE => $this->t('Table'),
    ];
  }

  /**
   * Get the card populate options.
   *
   * @return array
   *   An array of card populate options.
   */
  private function getCardPopulateOptions() {
    return [
      self::DISPLAY_CARD_POPULATE_AUTO => $this->t('Automatic'),
      self::DISPLAY_CARD_POPULATE_MANUAL => $this->t('Manual selection'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->config['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    $display = $this->config['display_form'] ?? [];
    $options = [];

    $articles = $this->getArticles();
    if (empty($articles)) {
      // Nothing to show.
      return;
    }

    $display_type = $display['type'] ?? self::DISPLAY_TYPE_CARDS;
    if ($display_type == self::DISPLAY_TYPE_CARDS) {
      $card_populate = $display['cards']['populate'] ?? self::DISPLAY_CARD_POPULATE_AUTO;
      if ($card_populate == self::DISPLAY_CARD_POPULATE_MANUAL) {
        $card_select = $display['cards']['select'] ?? [];

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
        $card_limit = $display['cards']['count'] ?? self::CARD_COUNT_DEFAULT;
        $card_limit = $card_limit <= self::CARD_LIMIT ? $card_limit : self::CARD_LIMIT;
        $articles = array_slice($articles, 0, $card_limit, TRUE);
      }
    }

    if (empty($articles)) {
      // Check again if we have something to show.
      return;
    }

    $build = [
      '#theme' => 'article_collection_' . ($display['type'] ?? self::DISPLAY_TYPE_CARDS),
      '#title' => $this->t('Article collection'),
      '#articles' => $articles,
      '#options' => [
        'columns' => 3,
      ] + $options,
    ];
    return $build;
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
    $section = $this->getContextValue('section');
    $tag_ids = $this->getApplicableTagIds();
    if (empty($tag_ids)) {
      return NULL;
    }
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
    $tag_ids = array_filter($this->config['article_selection_form']['tags']['tag_ids'] ?? []);
    return $tag_ids;
  }

  /**
   * Get the tag operation (conjunction).
   *
   * @return string
   *   Either 'AND' or 'OR'.
   */
  private function getTagConjunction() {
    return $this->config['article_selection_form']['tags']['tag_op'] ?? 'AND';
  }

  /**
   * Get the tag summary.
   *
   * @return string
   *   The summary string for the configured tags.
   */
  public function getTagSummary() {
    $tag_ids = $this->getApplicableTagIds();
    $tags = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($tag_ids);
    return implode(', ', array_map(function (Term $term) {
      return $term->label();
    }, $tags));
  }

  /**
   * Get the number of articles available in the current configuration.
   *
   * @return int
   *   The number of articles.
   */
  public function getArticleCount() {
    return count($this->getArticles());
  }

  /**
   * Get the display summary.
   *
   * @return string
   *   A string describing the current display configuration.
   */
  public function getDisplaySummary() {
    $options = $this->getDisplayTypeOptions();
    $display_type = $this->config['display_form']['type'] ?? self::DISPLAY_TYPE_CARDS;
    $summary_items = [
      $options[$display_type],
    ];
    if ($display_type == self::DISPLAY_TYPE_CARDS) {
      $card_options = $this->getCardPopulateOptions();
      $summary_items[] = $card_options[$this->config['display_form']['cards']['populate'] ?? self::DISPLAY_CARD_POPULATE_AUTO];
    }
    return implode(', ', $summary_items);
  }

}
