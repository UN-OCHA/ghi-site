<?php

namespace Drupal\ghi_content\Plugin\SectionMenuItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_sections\Menu\OptionalSectionMenuPluginInterface;
use Drupal\ghi_sections\Menu\SectionMenuItem;
use Drupal\ghi_sections\Menu\SectionMenuPluginBase;
use Drupal\ghi_sections\MenuItemType\SectionNode;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an article subpage item for section menus.
 *
 * @SectionMenuPlugin(
 *   id = "article_subpage",
 *   label = @Translation("Article subpage"),
 *   description = @Translation("This item links to an article of a section."),
 *   weight = 3,
 * )
 */
class ArticleSubpage extends SectionMenuPluginBase implements OptionalSectionMenuPluginInterface {

  /**
   * The subpage manager.
   *
   * @var \Drupal\ghi_content\ContentManager\ArticleManager
   */
  protected $articleManager;

  /**
   * The node type.
   *
   * @var string
   */
  protected $articleId;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->articleManager = $container->get('ghi_content.manager.article');
    $instance->articleId = $configuration['article_id'] ?? NULL;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    $article = $this->getArticle();
    return $article?->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getItem() {
    $article = $this->getArticle();
    if (!$article) {
      return NULL;
    }
    $item = new SectionMenuItem($this->getPluginId(), $this->getSection()->id(), $this->getLabel());
    return $item;
  }

  /**
   * {@inheritdoc}
   */
  public function getWidget() {
    $article = $this->getArticle();
    if (!$article) {
      return NULL;
    }
    $item = $this->getItem();
    $widget = new SectionNode($item->getLabel(), $article);
    return $widget;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return $this->getArticle()?->isPublished();
  }

  /**
   * {@inheritdoc}
   */
  public function isValid() {
    return $this->getArticle() instanceof Article;
  }

  /**
   * Get the article for the current menu item.
   *
   * @return \Drupal\ghi_content\Entity\Article|null
   *   The article node if found, or NULL otherwise.
   */
  private function getArticle() {
    if (!$this->articleId) {
      return NULL;
    }
    $article = $this->entityTypeManager->getStorage('node')->load($this->articleId);
    if (!$article instanceof Article) {
      return NULL;
    }
    $section = $this->getSection();
    if ($section && $section instanceof SectionNodeInterface) {
      $article->setContextNode($section);
    }
    return $article;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($form, FormStateInterface $form_state) {
    $options = $this->getArticleOptions();
    $form['article_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Article'),
      '#options' => $options,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable() {
    return !empty($this->getArticleOptions());
  }

  /**
   * Get the options for the article select.
   *
   * @return string[]
   *   An array with article labels as options, keyed by the article id.
   */
  private function getArticleOptions() {
    $section = $this->getSection();
    $articles = $this->articleManager->loadNodesForSection($section);

    $options = array_map(function (Article $article) {
      return $article->label();
    }, $articles);

    /** @var \Drupal\ghi_sections\Field\SectionMenuItemList $menu_item_list */
    $menu_item_list = clone $this->sectionMenuStorage->getSectionMenuItems();
    $exclude_article_ids = [];
    foreach ($menu_item_list->getAll() as $menu_item) {
      $plugin = $menu_item->getPlugin();
      if (!$plugin instanceof self || !$plugin->getArticle()) {
        continue;
      }
      if ($plugin->getSection()->id() == $section->id() && array_key_exists($plugin->getArticle()->id(), $articles)) {
        $data = $menu_item->toArray();
        $article_id = $data['configuration']['article_id'];
        $exclude_article_ids[$article_id] = $article_id;
      }
    }
    $options = array_diff_key($options, $exclude_article_ids);

    return $options;
  }

}
