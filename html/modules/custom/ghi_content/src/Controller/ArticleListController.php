<?php

namespace Drupal\ghi_content\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_sections\SectionManager;
use Drupal\ghi_sections\SectionTrait;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate_tools\MigrateBatchExecutable;
use Drupal\node\NodeInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for article lists.
 */
class ArticleListController extends ControllerBase {

  use SectionTrait;

  /**
   * The machine name of the view to use for this article list.
   */
  const VIEW_NAME = 'articles_by_tags';

  /**
   * The machine name of the views display to use for this article list.
   */
  const VIEW_DISPLAY = 'block_articles_table';

  /**
   * The route name for the article listing backend page.
   */
  const ARTICLE_LIST_ROUTE = 'view.content.page_articles';

  /**
   * The article manager.
   *
   * @var \Drupal\ghi_content\ContentManager\ArticleManager
   */
  protected $articleManager;

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * Public constructor.
   */
  public function __construct(ArticleManager $article_manager, MigrationPluginManagerInterface $migration_plugin_manager) {
    $this->articleManager = $article_manager;
    $this->migrationPluginManager = $migration_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ghi_content.manager.article'),
      $container->get('plugin.manager.migration'),
    );
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
  public function access(NodeInterface $node = NULL) {
    if ($node) {
      $section = $this->getSectionNode($node);
      return AccessResult::allowedIf($section && $section->access('update'));
    }
    return Url::fromRoute(self::ARTICLE_LIST_ROUTE)->access(NULL, TRUE);
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
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return array|null
   *   A render array.
   */
  public function listArticles(NodeInterface $node) {
    $node = $this->getSectionNode($node);
    if (!in_array($node->bundle(), SectionManager::SECTION_BUNDLES)) {
      return;
    }
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
      '#arguments' => array_keys($section_tags),
      '#embed' => TRUE,
    ];
    return $build;
  }

  /**
   * Callback for updating articles for a section from the remote source.
   *
   * This runs a migration in a batch process.
   *
   * @param \Drupal\node\NodeInterface|null $node
   *   An optional node object, which limits the migration to only those
   *   articles relevant to the given node via its tags.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
   *   A redirect to a batch url or a render array if the migration can't be
   *   found.
   */
  public function updateArticles(NodeInterface $node = NULL) {
    $section = $node ? $this->getSectionNode($node) : NULL;
    if ($node !== NULL && !$section) {
      $this->messenger()->addError($this->t('Invalid content object to run this migration.'));
      return new RedirectResponse($node->toUrl()->toString());
    }
    $redirect_url = Url::fromRoute(self::ARTICLE_LIST_ROUTE)->toString();
    $tags = NULL;
    if ($section) {
      $redirect_url = Url::fromRoute('ghi_content.node.articles', ['node' => $section->id()])->toString();
      $tags = $this->articleManager->getTags($section);
    }
    return $this->runArticlesMigration($redirect_url, $tags);
  }

  /**
   * Run the articles migrations.
   *
   * @param string $redirect
   *   The url to redirect to after the migration has finished.
   * @param array $tags
   *   Optional tags to filter the source data by.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
   *   A redirect to a batch url or a render array if the migration can't be
   *   found.
   */
  private function runArticlesMigration($redirect, array $tags = NULL) {
    // @todo Make this work with more than a single migration. One way to do
    // this, would be to fetch all definitions and then filter by the source
    // plugin used (RemoteSourceGraphQL).
    $migration = $this->migrationPluginManager->createInstance('articles_gho');
    if (empty($migration)) {
      return [
        '#markup' => $this->t('There was an error processing your request. Please contact an administrator.'),
      ];
    }
    $options = [
      'force' => 1,
    ];
    if ($tags !== NULL) {
      $options['configuration'] = ['source_tags' => $tags];
    }
    $executable = new MigrateBatchExecutable($migration, new MigrateMessage(), $options);
    $executable->batchImport();
    batch_process($redirect);
    $batch = batch_get();
    $url = $batch['url'];
    return new RedirectResponse($url->toString());
  }

}
