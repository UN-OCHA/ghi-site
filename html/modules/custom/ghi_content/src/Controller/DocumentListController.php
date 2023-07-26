<?php

namespace Drupal\ghi_content\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\ghi_content\ContentManager\DocumentManager;
use Drupal\ghi_sections\SectionManager;
use Drupal\ghi_sections\SectionTrait;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate_tools\MigrateBatchExecutable;
use Drupal\node\NodeInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for document lists.
 */
class DocumentListController extends ControllerBase {

  use SectionTrait;

  /**
   * The machine name of the view to use for this document list.
   */
  const VIEW_NAME = 'content_by_tags';

  /**
   * The machine name of the views display to use for this document list.
   */
  const VIEW_DISPLAY = 'block_documents_table';

  /**
   * The route name for the article listing backend page.
   */
  const DOCUMENT_LIST_ROUTE = 'view.content.page_documents';

  /**
   * The document manager.
   *
   * @var \Drupal\ghi_content\ContentManager\DocumentManager
   */
  protected $documentManager;

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * Public constructor.
   */
  public function __construct(DocumentManager $document_manager, MigrationPluginManagerInterface $migration_plugin_manager) {
    $this->documentManager = $document_manager;
    $this->migrationPluginManager = $migration_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ghi_content.manager.document'),
      $container->get('plugin.manager.migration'),
    );
  }

  /**
   * Access callback for document admin pages on sections.
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
    return Url::fromRoute(self::DOCUMENT_LIST_ROUTE)->access(NULL, TRUE);
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
    return $this->t('Document pages for <em>@title</em>', [
      '@title' => $node->label(),
    ]);
  }

  /**
   * Page callback for the document list admin page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return array|null
   *   A render array.
   */
  public function listDocuments(NodeInterface $node) {
    $node = $this->getSectionNode($node);
    if (!in_array($node->bundle(), SectionManager::SECTION_BUNDLES)) {
      return;
    }
    $section_tags = $this->documentManager->getTags($node);
    $view = Views::getView(self::VIEW_NAME);

    $build = [];
    $build[] = [
      '#markup' => '<p>' . $this->t('This list shows you all documents that are currently available in this section via the shared common section tags: <em>@section_tags</em>', [
        '@section_tags' => implode(', ', $section_tags),
      ]) . '</p>',
    ];

    if (!$view instanceof ViewExecutable || !$view->getDisplay(self::VIEW_DISPLAY)) {
      // Log the problem and add a message.
      $this->getLogger('views')->error('View @view or display @display not found.', [
        '@view' => self::VIEW_NAME,
        '@display' => self::VIEW_DISPLAY,
      ]);
      $this->messenger()->addError($this->t('There was a technical problem creating this document list. The issue has been logged and we will be looking at it as soon as possible.'));
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
   * Callback for updating documents for a section from the remote source.
   *
   * This runs a migration in a batch process.
   *
   * @param \Drupal\node\NodeInterface|null $node
   *   An optional node object, which limits the migration to only those
   *   documents relevant to the given node via its tags.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
   *   A redirect to a batch url or a render array if the migration can't be
   *   found.
   */
  public function updateDocuments(NodeInterface $node = NULL) {
    $section = $node ? $this->getSectionNode($node) : NULL;
    if ($node !== NULL && !$section) {
      $this->messenger()->addError($this->t('Invalid content object to run this migration.'));
      return new RedirectResponse($node->toUrl()->toString());
    }
    $redirect_url = Url::fromRoute(self::DOCUMENT_LIST_ROUTE)->toString();
    $tags = NULL;
    if ($section) {
      $redirect_url = Url::fromRoute('ghi_content.node.documents', ['node' => $section->id()])->toString();
      $tags = $this->documentManager->getTags($section);
    }
    return $this->runDocumentsMigration($redirect_url, $tags);
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
  private function runDocumentsMigration($redirect, array $tags = NULL) {
    // @todo Make this work with more than a single migration. One way to do
    // this, would be to fetch all definitions and then filter by the source
    // plugin used (RemoteSourceGraphQL).
    $migration = $this->migrationPluginManager->createInstance('documents_hpc_content_module');
    if (empty($migration)) {
      return [
        '#markup' => $this->t('There was an error processing your request. Please contact an administrator.'),
      ];
    }
    if ($tags !== NULL) {
      $options['configuration'] = ['source_tags' => $tags];
    }
    $executable = new MigrateBatchExecutable($migration, new MigrateMessage());
    $executable->batchImport();
    batch_process($redirect);
    $batch = batch_get();
    $url = $batch['url'];
    return new RedirectResponse($url->toString());
  }

}
