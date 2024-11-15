<?php

namespace Drupal\ghi_content\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Url;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\node\NodeInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for article lists.
 */
class ArticleListController extends ContentBaseListController {

  /**
   * The machine name of the view to use for this article list.
   */
  const VIEW_NAME = 'content_by_tags';

  /**
   * The machine name of the views display to use for this article list.
   */
  const VIEW_DISPLAY = 'block_articles_table';

  /**
   * The route name for the article listing backend page.
   */
  const ARTICLE_LIST_ROUTE = 'view.content.page_articles';

  /**
   * The migration id for articles.
   */
  const MIGRATION_ID = 'articles_hpc_content_module';

  /**
   * The article manager.
   *
   * @var \Drupal\ghi_content\ContentManager\ArticleManager
   */
  protected $articleManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->articleManager = $container->get('ghi_content.manager.article');
    return $instance;
  }

  /**
   * Access callback for article admin pages on sections.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(?NodeInterface $node = NULL) {
    if ($node) {
      return AccessResult::allowedIf($node instanceof SectionNodeInterface && $node->access('update'));
    }
    return Url::fromRoute(self::ARTICLE_LIST_ROUTE)->access(NULL, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function getMigrationId() {
    return self::MIGRATION_ID;
  }

  /**
   * Title callback for the article list admin page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return string
   *   The page title.
   */
  public function getTitle(NodeInterface $node) {
    return $this->t('Article pages for <em>@title</em>', [
      '@title' => $node->label(),
    ]);
  }

  /**
   * Page callback for the article list admin page.
   *
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $node
   *   The section node object.
   *
   * @return array|null
   *   A render array.
   */
  public function listArticles(SectionNodeInterface $node) {
    $section_tags = $this->articleManager->getTags($node);
    $view = Views::getView(self::VIEW_NAME);

    $build = [];
    $build[] = [
      '#markup' => '<p>' . $this->t('This list shows you all articles that are currently available in this section via the shared common section tags: <em>@section_tags</em>', [
        '@section_tags' => implode(', ', $section_tags),
      ]) . '</p>',
    ];

    if (!$view instanceof ViewExecutable || !$view->getDisplay(self::VIEW_DISPLAY)) {
      // Log the problem and add a message.
      $this->getLogger('views')->error('View @view or display @display not found.', [
        '@view' => self::VIEW_NAME,
        '@display' => self::VIEW_DISPLAY,
      ]);
      $this->messenger()->addError($this->t('There was a technical problem creating this article list. The issue has been logged and we will be looking at it as soon as possible.'));
      return $build;
    }
    $build[] = [
      '#type' => 'view',
      '#name' => self::VIEW_NAME,
      '#display_id' => self::VIEW_DISPLAY,
      '#arguments' => [
        implode(',', array_keys($section_tags)),
      ],
      '#embed' => TRUE,
    ];
    return $build;
  }

  /**
   * Callback for updating articles for a section from the remote source.
   *
   * This runs a migration in a batch process.
   *
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface|null $section
   *   An optional section node object, which limits the migration to only
   *   those articles relevant to the given node via its tags.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
   *   A redirect to a batch url or a render array if the migration can't be
   *   found.
   */
  public function updateArticles(?SectionNodeInterface $section = NULL) {
    $redirect_url = Url::fromRoute(self::ARTICLE_LIST_ROUTE)->toString();
    $tags = NULL;
    $section = $section ?? $this->routeMatch->getParameter('node');
    if ($section instanceof SectionNodeInterface) {
      $redirect_url = Url::fromRoute('ghi_content.node.articles', ['node' => $section->id()])->toString();
      $tags = $this->articleManager->getTags($section);
    }
    return $this->runMigration($redirect_url, $tags);
  }

}
