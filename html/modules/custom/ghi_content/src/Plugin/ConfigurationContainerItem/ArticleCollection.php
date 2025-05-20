<?php

namespace Drupal\ghi_content\Plugin\ConfigurationContainerItem;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Cache\Cache;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_form_elements\ConfigurationContainerItemCustomActionsInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerItemCustomActionTrait;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
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
  use ConfigurationContainerItemCustomActionTrait;
  use DependencySerializationTrait;

  const MAX_FEATURE_COUNT = 2;
  const CARD_LIMIT = 15;
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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->articleManager = $container->get('ghi_content.manager.article');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomActions() {
    return [
      'article_selection_form' => $this->t('Article tags'),
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
    $section_tags = $section instanceof SectionNodeInterface ? $this->articleManager->getTags($section) : [];
    $section_tag_ids = array_keys($section_tags);

    // Get the defaults.
    $default_tags = $this->getSubmittedValue($element, $form_state, 'tags') ?? ($this->config['article_selection_form']['tags'] ?? []);
    $default_tag_ids = array_filter(array_map(function ($item) {
      return $item['target_id'] ?? ($item ?: NULL);
    }, $default_tags['tag_ids'] ?: []));

    $default_tags['tag_ids'] = array_values(array_map(function ($tag_id) {
      return ['target_id' => $tag_id];
    }, array_combine($section_tag_ids, $section_tag_ids) + array_combine($default_tag_ids, $default_tag_ids)));

    $wrapper_id = self::getWrapperId($element);
    $element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['#suffix'] = '</div>';
    $element['tags'] = [
      '#type' => 'tag_autocomplete',
      '#title' => $this->t('Tags'),
      '#description' => $this->t('Select tags to control which articles should be included in this article collection.'),
      '#default_value' => $default_tags,
      '#disabled_tags' => $section_tag_ids,
      '#ajax' => [
        'event' => 'change',
        'callback' => [$this, 'updateArticleCountPreview'],
        'wrapper' => $wrapper_id,
      ],
    ];
    if (!empty($section_tags)) {
      $element['tags']['#description'] .= ' ' . $this->t('The greyed out tags <em>@tags</em> are derived from the current page context and cannot be removed.', [
        '@tags' => implode(', ', $section_tags),
      ]);
    }
    $element['preview_summary_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['preview-summary-wrapper'],
      ],
      'summary' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => [
          'class' => ['preview-summary'],
        ],
        '#value' => $this->getTagCombinationSummary(),
      ],
    ];

    $element['#attached'] = [
      'library' => ['ghi_content/admin.article_collection'],
    ];
    return $element;
  }

  /**
   * Build the display subform.
   */
  public function displayForm($element, FormStateInterface $form_state) {
    $element['type'] = [
      '#type' => 'value',
      '#value' => array_key_first($this->getDisplayTypeOptions()),
    ];

    $type_selector = FormElementHelper::getStateSelector($element, ['type']);
    $element['cards'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $element['cards']['populate'] = [
      '#type' => 'radios',
      '#title' => $this->t('Population method'),
      '#description' => $this->t('Automatic selection will show the N most recently published articles, in reverse date order. Manual selection allows you to choose which articles to show (and which to highlight).'),
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
   * Update the article count preview based on the current tag selection.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response with commands to update the relevant part of the form.
   */
  public function updateArticleCountPreview(array &$form, FormStateInterface $form_state) {
    // Get the parents and array parents of the triggering element, which can
    // only be one of the child elements of the tag_autocomplete form element.
    $parents = $form_state->getTriggeringElement()['#parents'];
    $array_parents = $form_state->getTriggeringElement()['#array_parents'];
    array_pop($parents);
    array_pop($array_parents);

    // Get the values and update the config so that self::getArticleCount() has
    // everything it needs to fetch the articles.
    $values = $form_state->getValue($parents);
    $values['tag_ids'] = array_map(function ($item) {
      return ['target_id' => $item->entity_id];
    }, json_decode($values['tag_ids']) ?? []);
    $this->config['article_selection_form']['tags'] = $values;

    // Get the selector so we know where the updated preview value must go.
    $wrapper_id = NestedArray::getValue($form, array_merge($array_parents, ['#ajax', 'wrapper']));
    $selector = '#' . $wrapper_id . ' .preview-summary-wrapper .preview-summary';
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand($selector, $this->getTagCombinationSummary()));
    return $response;
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

    $section = $this->getContextValue('section');
    if ($section instanceof SectionNodeInterface) {
      $articles = array_map(function (Article $article) use ($section) {
        $article->setContextNode($section);
        return $article;
      }, $articles);
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
   * @param bool $published
   *   Whether to restrict to published nodes. Defaults to TRUE.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]|null
   *   An array of entity objects indexed by their ids.
   */
  private function getArticles($limit = NULL, $published = TRUE) {
    $section = $this->getContextValue('section');
    $tag_ids = $this->getApplicableTagIds();
    if (empty($tag_ids)) {
      return NULL;
    }
    $tag_conjunction = $this->getTagConjunction();
    if ($section || $tag_ids) {
      return $this->articleManager->loadNodesForTags($tag_ids, $section, $tag_conjunction, $limit, $published);
    }
    return $this->articleManager->loadAllNodes($limit);
  }

  /**
   * Get the currently configured tag ids.
   *
   * This works with both the old style configuration of an array of ids as
   * well as with the new style configuration with an array of arrays having a
   * target_id key.
   *
   * @return int[]
   *   An array of tag ids keyed by tag id.
   */
  private function getConfiguredTagIds() {
    $tag_ids = [];
    foreach ($this->config['article_selection_form']['tags']['tag_ids'] ?? [] as $item) {
      if (is_int($item)) {
        $tag_ids[$item] = $item;
      }
      elseif (is_array($item) && !empty($item['target_id'])) {
        $tag_ids[$item['target_id']] = $item['target_id'];
      }
      elseif ((int) $item != 0 && is_int((int) $item)) {
        $tag_ids[$item] = (int) $item;
      }
    }
    return $tag_ids;
  }

  /**
   * Get the applicabble tags for this block.
   *
   * @return array
   *   The tags that should be applied for article selection.
   */
  private function getApplicableTagIds() {
    $section = $this->getContextValue('section');
    $section_tags = $section ? $this->articleManager->getTags($section) : [];
    $section_tag_ids = array_keys($section_tags);
    $configured_tags = array_filter($this->getConfiguredTagIds());
    $tag_ids = array_combine($section_tag_ids, $section_tag_ids) + $configured_tags;
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
   * Get a translated summary string for the tag combination.
   *
   * @return \Drupal\Core\StringTranslation\PluralTranslatableMarkup
   *   The summary text.
   */
  private function getTagCombinationSummary() {
    return $this->formatPlural($this->getArticleCount(), '@count article found for this combination of tags', '@count articles found for this combination of tags');
  }

  /**
   * Get the number of articles available in the current configuration.
   *
   * @return int
   *   The number of articles.
   */
  public function getArticleCount() {
    return count($this->getArticles() ?? []);
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
      $populate_method = $this->config['display_form']['cards']['populate'] ?? self::DISPLAY_CARD_POPULATE_AUTO;
      $item = $card_options[$populate_method];
      if ($populate_method == self::DISPLAY_CARD_POPULATE_AUTO) {
        $count = $this->config['display_form']['cards']['count'] ?? self::CARD_COUNT_DEFAULT;
        $item .= ' (' . $count . ')';
      }
      if ($populate_method == self::DISPLAY_CARD_POPULATE_MANUAL) {
        $selected = $this->config['display_form']['cards']['select']['selected'];
        $count = !empty($selected) ? count($selected) : $this->t('up to @count', ['@count' => self::CARD_LIMIT]);
        $item .= ' (' . $count . ')';
      }
      $summary_items[] = $item;
    }
    return implode(', ', $summary_items);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $articles = $this->getArticles(NULL, FALSE) ?? [];
    $cache_tags = [];
    foreach ($articles as $article) {
      $cache_tags = Cache::mergeTags($cache_tags, $article->getCacheTags());
    }
    return $cache_tags;
  }

}
