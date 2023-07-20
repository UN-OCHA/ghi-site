<?php

namespace Drupal\ghi_content\ContentManager;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\RequestStack;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_content\Import\ImportManager;
use Drupal\ghi_content\RemoteContent\RemoteContentInterface;
use Drupal\ghi_content\RemoteContent\RemoteDocumentInterface;
use Drupal\ghi_content\RemoteSource\RemoteSourceManager;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\node\NodeInterface;

/**
 * Document manager service class.
 */
class DocumentManager extends BaseContentManager {

  use LayoutEntityHelperTrait;

  /**
   * The machine name of the bundle to use for documents.
   */
  const DOCUMENT_BUNDLE = 'document';

  /**
   * The machine name of the field that holds the remove document.
   */
  const REMOTE_DOCUMENT_FIELD = 'field_remote_document';

  /**
   * The machine name of the form element to use for displaying source links.
   */
  const REMOTE_SOURCE_LINK_TYPE = 'ghi_remote_document_source_link';

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $migrationManager;

  /**
   * The remote source manager.
   *
   * @var \Drupal\ghi_content\Import\ImportManager
   */
  protected $importManager;

  /**
   * Constructs a document manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, AccountInterface $current_user, MigrationPluginManager $migration_manager, RemoteSourceManager $remote_source_manager, ImportManager $import_manager, RequestStack $request_stack) {
    parent::__construct($entity_type_manager, $renderer, $current_user, $request_stack, $remote_source_manager);
    $this->migrationManager = $migration_manager;
    $this->importManager = $import_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getNodeBundle() {
    return self::DOCUMENT_BUNDLE;
  }

  /**
   * {@inheritdoc}
   */
  protected function getRemoteFieldName() {
    return self::REMOTE_DOCUMENT_FIELD;
  }

  /**
   * {@inheritdoc}
   */
  protected function getRemoteSourceLinkType() {
    return self::REMOTE_SOURCE_LINK_TYPE;
  }

  /**
   * {@inheritdoc}
   */
  public function loadNodeForRemoteContent(RemoteContentInterface $content) {
    $results = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => $this->getNodeBundle(),
      $this->getRemoteFieldName() . '.remote_source' => $content->getSource()->getPluginId(),
      $this->getRemoteFieldName() . '.document_id' => $content->getId(),
    ]);
    return $results && !empty($results) ? reset($results) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadRemoteContentForNode(NodeInterface $node, $refresh = FALSE) {
    $remote_field = $this->getRemoteFieldName();
    if (!$node->hasField($remote_field) || $node->get($remote_field)->isEmpty()) {
      return;
    }
    $remote_source = $node->get($remote_field)->remote_source;
    $document_id = $node->get($remote_field)->document_id;
    /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceInterface $remote_source_instance */
    $remote_source_instance = $this->remoteSourceManager->createInstance($remote_source);
    if ($refresh) {
      $remote_source_instance->disableCache();
    }
    return $remote_source_instance->getDocument($document_id);
  }

  /**
   * Create a local document node for the given remote document.
   *
   * @param \Drupal\ghi_content\RemoteContent\RemoteDocumentInterface $document
   *   An document object from the remote source.
   * @param string $title
   *   An title for the document node.
   * @param int $team
   *   An optional term id for the team field.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The created document node if successful or NULL otherwise.
   */
  public function createNodeFromRemoteDocument(RemoteDocumentInterface $document, $title, $team = NULL) {
    $node = $this->loadNodeForRemoteContent($document);
    if ($node) {
      // We allow only a single local document per remote document.
      return FALSE;
    }
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => self::DOCUMENT_BUNDLE,
      'title' => $title,
      'uid' => $this->currentUser->id(),
      'status' => FALSE,
    ]);
    $node->{self::REMOTE_DOCUMENT_FIELD} = [
      0 => [
        'remote_source' => $document->getSource()->getPluginId(),
        'document_id' => $document->getId(),
      ],
    ];
    if ($team) {
      $node->field_team = $team;
    }
    $status = $node->save();
    return $status == SAVED_NEW ? $node : NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function getMigration(NodeInterface $node) {
    if (!$node->hasField(self::REMOTE_DOCUMENT_FIELD) || $node->get(self::REMOTE_DOCUMENT_FIELD)->isEmpty()) {
      return;
    }
    $remote_source = $node->get(self::REMOTE_DOCUMENT_FIELD)->remote_source;
    $migrations = $this->migrationManager->getDefinitions();
    foreach ($migrations as $key => $def) {
      if (empty($def['source'])) {
        continue;
      }
      if (empty($def['source']['remote_source']) || $def['source']['remote_source'] != $remote_source) {
        continue;
      }
      // This is a candidate for a migration. Now let's look up the idmap.
      /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
      $migration = $this->migrationManager->createInstance($key);
      if (!$migration) {
        continue;
      }
      $source_id = $migration->getIdMap()->lookupSourceId(['nid' => $node->id()]);
      if ($source_id) {
        return $migration;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateNodeFromRemote(NodeInterface $node, $dry_run = FALSE, $reset = FALSE) {
    $remote_field = self::REMOTE_DOCUMENT_FIELD;
    $document = $this->loadRemoteContentForNode($node, TRUE);
    if (!$document) {
      return;
    }

    // See if the article needs a cleanup.
    $remote_source = $node->get($remote_field)->remote_source;
    $document_id = $node->get($remote_field)->article_id;
    $remote_source_original = $node->original ? $node->original->get($remote_field)->remote_source : NULL;
    $document_id_original = $node->original ? $node->original->get($remote_field)->article_id : NULL;
    $changed_article = $remote_source_original && $document_id_original && ($remote_source != $remote_source_original || $document_id != $document_id_original);
    $cleanup = $reset || $changed_article;

    // Set the base properties.
    $node->setTitle($document->getTitle());
    $node->setCreatedTime($document->getCreated());
    $node->setChangedTime($document->getUpdated());

    // Import the short title.
    $this->importManager->importTextfield($node, $document, $this->t('Short title'), 'getShortTitle', 'field_short_title');

    // Import the summary.
    $this->importManager->importTextfield($node, $document, $this->t('Summary'), 'getSummary', 'field_summary', 'html_text');

    // Import the image.
    $this->importManager->importImage($node, $document, 'field_image');

    // Import the paragraphs for the article.
    $this->importManager->importDocumentChapters($node, $document, [], NULL, $cleanup);

    // Import the tags.
    $this->importManager->importTags($node, $document, 'field_tags');

    if (!$dry_run) {
      $this->importManager->layoutManagerDiscardChanges($node, NULL);
    }
  }

  /**
   * Check if the given node is in-sync with its remote source.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return bool|null
   *   TRUE if in-sync, FALSE if not and NULL if the migration is not found.
   *
   * @see ghi_content_form_node_article_edit_form_alter()
   */
  public function isUpToDateWithRemote(NodeInterface $node) {
    $migration = $this->getMigration($node);
    if (!$migration) {
      return NULL;
    }

    // First load the original unchanged node as this function is called from a
    // form_alter hook and some of the widgets tinker with the field structure
    // to support their needs. For comparison we need to use the node object as
    // it's currently stored in the database.
    $original_node = $this->entityTypeManager->getStorage('node')->loadUnchanged($node->id());

    // First get the local data.
    $local_data = $this->normalizeContentNodeData($original_node);
    $local_data['chapters'] = $this->importManager->getLocalDocumentChapterUuids($original_node);

    // Then get the remote data by pretending to do an update on the node.
    $updated_node = clone $original_node;
    $this->updateNodeFromRemote($updated_node, TRUE);
    $document = $this->loadRemoteContentForNode($original_node, TRUE);

    $remote_data = $this->normalizeContentNodeData($updated_node);
    $remote_data['chapters'] = $this->importManager->getRemoteDocumentChapterUuids($document);

    // Calculate the checksums and compare.
    $local_checksum = md5(str_replace('"', '', json_encode($local_data)));
    $remote_checksum = md5(str_replace('"', '', json_encode($remote_data)));
    return $local_checksum === $remote_checksum;
  }

}
