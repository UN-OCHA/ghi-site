<?php

namespace Drupal\ghi_content\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_sections\SectionManager;
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

  /**
   * The machine name of the view to use for this article list.
   */
  const VIEW_NAME = 'articles_by_tags';

  /**
   * The machine name of the views display to use for this article list.
   */
  const VIEW_DISPLAY = 'block_articles_table';

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
  public function access(NodeInterface $node) {
    return AccessResult::allowedIf($node->access('update') && in_array($node->bundle(), SectionManager::SECTION_BUNDLES));
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
    return $this->t('Articles for <em>@title</em>', [
      '@title' => $node->label(),
    ]);
  }

  /**
   * Page callback for the article list admin page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return array
   *   A render array.
   */
  public function listArticles(NodeInterface $node) {
    $section_tags = $this->articleManager->getTags($node);
    $view = Views::getView(self::VIEW_NAME);

    $build = [];
    $build[] = [
      '#markup' => '<p>' . $this->t('This list shows you all articles that are available in this section via the shared common section tags: <em>@section_tags</em>', [
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
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to a batch url.
   */
  public function updateArticles(NodeInterface $node) {
    $migration = $this->migrationPluginManager->createInstance('articles_gho');
    if (empty($migration)) {
      return [
        '#markup' => $this->t('There was an error processing your request. Please contact an administrator.'),
      ];
    }

    $options = [
      'limit' => 5,
      'update' => 1,
      'force' => 1,
      'configuration' => [
        'source_tags' => $this->articleManager->getTags($node),
        'limit' => 5,
        'update' => 1,
        'force' => 1,
      ],
    ];
    $executable = new MigrateBatchExecutable($migration, new MigrateMessage(), $options);
    $executable->batchImport();
    batch_process(Url::fromRoute('ghi_content.node.articles', ['node' => $node->id()])->toString());
    $batch = batch_get();
    $url = $batch['url'];
    return new RedirectResponse($url->toString());
  }

}
