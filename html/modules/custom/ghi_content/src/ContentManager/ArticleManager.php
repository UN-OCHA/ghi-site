<?php

namespace Drupal\ghi_content\ContentManager;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_content\Import\ImportManager;
use Drupal\ghi_content\RemoteContent\RemoteArticleInterface;
use Drupal\ghi_content\RemoteSource\RemoteSourceManager;
use Drupal\ghi_sections\SectionManager;
use Drupal\ghi_sections\SectionTrait;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate\Row;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Article manager service class.
 */
class ArticleManager extends BaseContentManager {

  use SectionTrait;

  /**
   * Default mode for new directories. See self::chmod().
   */
  const IMAGE_DIRECTORY = 'public://article-images';

  /**
   * The machine name of the bundle to use for articles.
   */
  const ARTICLE_BUNDLE = 'article';

  /**
   * The machine name of the field that holds the remove article.
   */
  const REMOTE_ARTICLE_FIELD = 'field_remote_article';

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $migrationManager;

  /**
   * The remote source manager.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager
   */
  protected $remoteSourceManager;

  /**
   * The remote source manager.
   *
   * @var \Drupal\ghi_content\Import\ImportManager
   */
  protected $importManager;

  /**
   * Constructs a document manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, AccountInterface $current_user, MigrationPluginManager $migration_manager, RemoteSourceManager $remote_source_manager, ImportManager $import_manager) {
    parent::__construct($entity_type_manager, $renderer, $current_user);
    $this->migrationManager = $migration_manager;
    $this->remoteSourceManager = $remote_source_manager;
    $this->importManager = $import_manager;
  }

  /**
   * Create a local article node for the given remote article.
   *
   * @param \Drupal\ghi_content\RemoteContent\RemoteArticleInterface $article
   *   An article object from the remote source.
   * @param string $title
   *   An title for the article node.
   * @param int $team
   *   An optional term id for the team field.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The created article node if successful or NULL otherwise.
   */
  public function createNodeFromRemoteArticle(RemoteArticleInterface $article, $title, $team = NULL) {
    $node = $this->loadNodeForRemoteArticle($article);
    if ($node) {
      // We allow only a single local article per remote article.
      return FALSE;
    }
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => self::ARTICLE_BUNDLE,
      'title' => $title,
      'uid' => $this->currentUser->id(),
      'status' => FALSE,
    ]);
    $node->{self::REMOTE_ARTICLE_FIELD} = [
      0 => [
        'remote_source' => $article->getSource()->getPluginId(),
        'article_id' => $article->getId(),
      ],
    ];
    if ($team) {
      $node->field_team = $team;
    }
    $status = $node->save();
    return $status == SAVED_NEW ? $node : NULL;
  }

  /**
   * Load a local article node for the given remote article.
   *
   * @param \Drupal\ghi_content\RemoteContent\RemoteArticleInterface $article
   *   An article object from the remote source.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The article node if found or NULL.
   */
  public function loadNodeForRemoteArticle(RemoteArticleInterface $article) {
    $results = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => self::ARTICLE_BUNDLE,
      self::REMOTE_ARTICLE_FIELD . '.remote_source' => $article->getSource()->getPluginId(),
      self::REMOTE_ARTICLE_FIELD . '.article_id' => $article->getId(),
    ]);
    return $results && !empty($results) ? reset($results) : NULL;
  }

  /**
   * Load the article for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param bool $refresh
   *   Wether to retrieve fresh data.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteArticleInterface|null
   *   The remote article object if found.
   */
  public function loadArticleForNode(NodeInterface $node, $refresh = FALSE) {
    $remote_field = self::REMOTE_ARTICLE_FIELD;
    if (!$node->hasField($remote_field)) {
      return;
    }

    $remote_source = $node->get($remote_field)->remote_source;
    $article_id = $node->get($remote_field)->article_id;

    /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceInterface $remote_source_instance */
    $remote_source_instance = $this->remoteSourceManager->createInstance($remote_source);
    if ($refresh) {
      $remote_source_instance->disableCache();
    }
    return $remote_source_instance->getArticle($article_id);
  }

  /**
   * Load all articles for a given set of tags.
   *
   * @param array $tags
   *   An array of tags. This can be either an array of term objects, or an
   *   array if term ids.
   * @param \Drupal\node\NodeInterface $node
   *   Optional: A node object which tags serve as a base context.
   * @param string $op
   *   The logical operator (conjunction) for combining the tags.
   * @param int $limit
   *   An optional limit.
   * @param bool $published
   *   An optional flag to restrict this to published nodes. Default is TRUE.
   *
   * @return \Drupal\node\NodeInterface[]|null
   *   An array of entity objects indexed by their ids.
   */
  public function loadNodesForTags(array $tags = NULL, NodeInterface $node = NULL, $op = 'AND', $limit = NULL, $published = TRUE) {
    if (empty($tags) && $node === NULL) {
      return NULL;
    }

    $tag_field = 'field_tags';

    // Setup the base query.
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    if ($published) {
      $query->condition('status', NodeInterface::PUBLISHED);
      $query->accessCheck();
    }
    $query->condition('type', self::ARTICLE_BUNDLE);
    if ($limit !== NULL) {
      $query->pager((int) $limit);
    }

    // For the logic behing the following conditions on tags see comments on
    // https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Entity!Query!QueryInterface.php/function/QueryInterface%3A%3AandConditionGroup/8.2.x
    if ($node) {
      // Get the base tags, these must all be present in the articles.
      $node_tags = $this->getTags($node);
      foreach (array_keys($node_tags) as $tag_id) {
        $query->condition($this->getLogicalQueryCondition($query, 'AND', $tag_field, $tag_id));
      }
    }

    // Assemble the given tags into query conditions.
    $tags = $tags ?? [];
    $tag_ids = array_filter(array_map(function ($tag) {
      if (is_object($tag) && $tag instanceof TermInterface) {
        return $tag->id();
      }
      if (is_scalar($tag) && intval($tag)) {
        return intval($tag);
      }
    }, $tags));

    $condition = $op == 'AND' ? $query->andConditionGroup() : $query->orConditionGroup();
    foreach ($tag_ids as $tag_id) {
      $condition->condition($this->getLogicalQueryCondition($query, $op, $tag_field, $tag_id));
    }
    if ($condition->count()) {
      $query->condition($condition);
    }

    $results = $query->execute();
    return $this->entityTypeManager->getStorage('node')->loadMultiple($results);
  }

  /**
   * Add a logical condition to the query.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The query object to modify.
   * @param string $op
   *   The logical operation, either 'AND' or 'OR'.
   * @param string $field
   *   The field for the condition.
   * @param mixed $value
   *   The value for the condition.
   *
   * @return \Drupal\Core\Entity\Query\ConditionInterface
   *   A condition object.
   */
  private function getLogicalQueryCondition(QueryInterface $query, $op, $field, $value) {
    if ($op == 'AND') {
      $condition = $query->andConditionGroup();
      $condition->condition($field, $value);
    }
    else {
      $condition = $query->orConditionGroup();
      $condition->condition($field, $value);
    }
    return $condition;
  }

  /**
   * Load all articles for a section.
   *
   * @param \Drupal\node\NodeInterface $section
   *   The section that articles belong to.
   *
   * @return \Drupal\node\NodeInterface[]|null
   *   An array of node objects indexed by their ids.
   */
  public function loadNodesForSection(NodeInterface $section) {
    if (!$this->isSectionNode($section)) {
      return NULL;
    }
    return $this->loadNodesForTags(NULL, $section, 'AND');
  }

  /**
   * Load all sections where the given node can appear.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Array of section nodes.
   */
  public function loadSectionsForNode(NodeInterface $node) {
    $sections = [];

    $tags = $this->getTags($node);
    if (empty($tags)) {
      return $sections;
    }

    // Setup the base query.
    $section_candidates = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => SectionManager::SECTION_BUNDLES,
      'field_tags' => array_keys($tags),
    ]);

    foreach ($section_candidates as $section) {
      $section_tags = $this->getTags($section);
      if (count(array_diff_key($section_tags, $tags)) > 0) {
        // We only want to keep sections where all tags are part of the article
        // tags. But here we have at least one section tag that is not present
        // on the article, so we skip this section.
        continue;
      }
      $sections[] = $section;
    }

    return $sections;
  }

  /**
   * Load major tags for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return array
   *   An array with term ids as keys and term labels as values.
   */
  public function getTags(NodeInterface $node) {
    $tags = [];
    if (!$node->hasField('field_tags')) {
      // @todo This should probably return FALSE or throw an exception.
      return $tags;
    }
    $entities = $node->get('field_tags')->referencedEntities();
    if (empty($entities)) {
      return $tags;
    }
    foreach ($entities as $tag) {
      $tags[$tag->id()] = $tag->label();
    }
    return $tags;
  }

  /**
   * Load available tags for a section.
   *
   * @param \Drupal\node\NodeInterface $section
   *   The section.
   *
   * @return array
   *   An array with term ids as keys and term labels as values.
   */
  public function loadAvailableTagsForSection(NodeInterface $section) {
    if ($section->bundle() != 'section') {
      return NULL;
    }
    $nodes = $this->loadNodesForSection($section);
    $section_tags = $this->getTags($section);
    $article_tags = $this->getAvailableTags($nodes);
    return array_unique($section_tags + $article_tags);
  }

  /**
   * Load available tags for a section.
   *
   * @param \Drupal\node\NodeInterface[] $nodes
   *   The nodes to extract the tags from.
   *
   * @return array
   *   An array with term ids as keys and term labels as values.
   */
  public function getAvailableTags(array $nodes) {
    $tags = [];
    foreach ($nodes as $node) {
      $tags = $tags + $this->getTags($node);
    }
    return $tags;
  }

  /**
   * Group node ids by associated tags.
   *
   * @param \Drupal\node\NodeInterface[] $nodes
   *   The nodes to process.
   * @param array $additional_tags
   *   An optional array of additional tags to apply to every node.
   *
   * @return array
   *   An array with term ids as keys. The values are arrays of node ids.
   */
  public function getNodeIdsGroupedByTag(array $nodes, array $additional_tags = []) {
    $tags = [];
    foreach ($nodes as $node) {
      foreach (array_unique($additional_tags + $this->getTags($node)) as $id => $tag) {
        $tags[$id][$node->id()] = $node->id();
      }
    }
    return $tags;
  }

  /**
   * Load all articles.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]|null
   *   An array of entity objects indexed by their ids.
   */
  public function loadAllNodes() {
    $nodes = $this->entityTypeManager->getStorage('node')
      ->loadByProperties([
        'type' => self::ARTICLE_BUNDLE,
        'status' => NodeInterface::PUBLISHED,
      ]);
    return $nodes;
  }

  /**
   * Get fully rendered nodes.
   *
   * @param \Drupal\node\NodeInterface[] $articles
   *   The list of articles to render.
   * @param string $view_mode
   *   The view mode to use for rendering.
   *
   * @return array
   *   An array with term ids as keys and term labels as values.
   */
  public function getNodePreviews(array $articles, $view_mode) {
    $previews = [];
    foreach ($articles as $article) {
      $build = $this->entityTypeManager->getViewBuilder('node')->view($article, $view_mode);
      $previews[$article->id()] = $this->renderer->render($build);
    }
    return $previews;
  }

  /**
   * Cleanup after an article has been deleted.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @see ghi_content_node_predelete()
   */
  public function cleanupArticleOnDelete(NodeInterface $node) {
    if ($node->bundle() != self::ARTICLE_BUNDLE) {
      return;
    }
    $this->removeMigrationMapEntries($node);
  }

  /**
   * Get the migration for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \\Drupal\migrate\Plugin\MigrationInterface|null
   *   The migration plugin if found.
   */
  private function getMigration(NodeInterface $node) {
    if (!$node->hasField(self::REMOTE_ARTICLE_FIELD) || $node->get(self::REMOTE_ARTICLE_FIELD)->isEmpty()) {
      return;
    }
    $remote_source = $node->get(self::REMOTE_ARTICLE_FIELD)->remote_source;
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
   * Remove migration map entries for the given node.
   *
   * Doing this, allows to re-import a previously imported article that has
   * been deleted on the backend. This is more of an user-1 rescue thing to do.
   * Generally, articles can't be deleted in the backend but need to be removed
   * (unpublished/deleted) from the remote source.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   */
  private function removeMigrationMapEntries(NodeInterface $node) {
    $migration = $this->getMigration($node);
    if (!$migration) {
      return;
    }
    $source_id = $migration->getIdMap()->lookupSourceId(['nid' => $node->id()]);
    $migration->getIdMap()->delete($source_id);
  }

  /**
   * Update the given node according to the data on its remote source.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   * @param bool $dry_run
   *   Whether the update should actually modify data.
   * @param bool $reset
   *   Whether article node should be reset to it's original state (as if it
   *   would be created right now based on the configuration on the remote).
   *
   * @see ghi_content_node_presave()
   */
  public function updateNodeFromRemote(NodeInterface $node, $dry_run = FALSE, $reset = FALSE) {
    $remote_field = self::REMOTE_ARTICLE_FIELD;
    $article = $this->loadArticleForNode($node, TRUE);
    if (!$article) {
      return;
    }

    // See if the article needs a cleanup.
    $remote_source = $node->get($remote_field)->remote_source;
    $article_id = $node->get($remote_field)->article_id;
    $remote_source_original = $node->original ? $node->original->get($remote_field)->remote_source : NULL;
    $article_id_original = $node->original ? $node->original->get($remote_field)->article_id : NULL;
    $changed_article = $remote_source_original && $article_id_original && ($remote_source != $remote_source_original || $article_id != $article_id_original);
    $cleanup = $reset || $changed_article;

    // Set the base properties.
    $node->setTitle($article->getTitle());
    $node->setCreatedTime($article->getCreated());
    $node->setChangedTime($article->getUpdated());

    // Import the image.
    $this->importManager->importImage($node, $article, 'field_image');

    // Import the paragraphs for the article.
    $this->importManager->importParagraphs($node, $article, [], NULL, $cleanup);

    // Import the tags.
    $this->importManager->importTags($node, $article, 'field_tags');

    if ($node->isNew()) {
      $this->importManager->setupRelatedArticlesElement($node, $article);
    }

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
    $local_data = $this->normalizeArticleNodeData($original_node);
    $local_data['paragraphs'] = $this->importManager->getLocalArticleParagraphUuids($original_node);

    // Then get the remote data by pretending to do an update on the node.
    $updated_node = clone $original_node;
    $this->updateNodeFromRemote($updated_node, TRUE);
    $article = $this->loadArticleForNode($original_node, TRUE);

    $remote_data = $this->normalizeArticleNodeData($updated_node);
    $remote_data['paragraphs'] = $this->importManager->getRemoteArticleParagraphUuids($article);

    // Calculate the checksums and compare.
    $local_checksum = md5(str_replace('"', '', json_encode($local_data)));
    $remote_checksum = md5(str_replace('"', '', json_encode($remote_data)));
    return $local_checksum === $remote_checksum;
  }

  /**
   * Normalize an article node for comparision between local and remote data.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object to normalize.
   *
   * @return array
   *   A normalized array based on the given node object.
   */
  private function normalizeArticleNodeData(NodeInterface $node) {
    $data = $node->toArray();
    unset($data['changed']);
    ArrayHelper::sortMultiDimensionalArrayByKeys($data);
    ArrayHelper::reduceArray($data);
    return $data;
  }

  /**
   * Update the migration state of the given node.
   *
   * This is usefull when manually importing the source data.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @see ghi_content_form_node_article_edit_form_submit()
   */
  public function updateMigrationState(NodeInterface $node) {
    $migration = $this->getMigration($node);
    if (!$migration) {
      return;
    }

    $migrate_executable = new MigrateExecutable($migration);

    /** @var \Drupal\ghi_content\Plugin\migrate\source\RemoteSourceGraphQL $source */
    $source = $migration->getSourcePlugin();
    $source_id = $migration->getIdMap()->lookupSourceId(['nid' => $node->id()]);
    $destination = $migration->getDestinationPlugin();

    $source_iterator = $source->initializeIterator();
    $source_iterator->rewind();
    foreach ($source_iterator as $row_data) {
      $row = new Row($row_data + $migration->getSourceConfiguration(), $source_id);
      if ($source_id != $row->getSourceIdValues()) {
        continue;
      }
      $migrate_executable->processRow($row);
      $id_map = $migration->getIdMap()->getRowBySource($row->getSourceIdValues());
      if (!$id_map) {
        continue;
      }

      $row->setIdMap($id_map);
      $row->rehash();

      $destination_ids = $migration->getIdMap()->lookupDestinationIds($source_id);
      $destination_id_values = $destination_ids ? reset($destination_ids) : [];
      $destination->import($row, $destination_id_values);
      $migration->getIdMap()->saveIdMapping($row, $destination_id_values, MigrateIdMapInterface::STATUS_IMPORTED, $destination->rollbackAction());
    }
  }

}
