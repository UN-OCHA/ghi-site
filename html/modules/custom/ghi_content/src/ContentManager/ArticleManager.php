<?php

namespace Drupal\ghi_content\ContentManager;

use Drupal\ghi_content\RemoteContent\RemoteArticleInterface;
use Drupal\ghi_content\RemoteContent\RemoteContentInterface;
use Drupal\node\NodeInterface;

/**
 * Article manager service class.
 */
class ArticleManager extends BaseContentManager {

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
   * The machine name of the form element to use for displaying source links.
   */
  const REMOTE_SOURCE_LINK_TYPE = 'ghi_remote_article_source_link';

  /**
   * {@inheritdoc}
   */
  public function getNodeBundle() {
    return self::ARTICLE_BUNDLE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteFieldName() {
    return self::REMOTE_ARTICLE_FIELD;
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
      $this->getRemoteFieldName() . '.article_id' => $content->getId(),
    ]);
    return $results && !empty($results) ? reset($results) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadRemoteContentForNode(NodeInterface $node, $refresh = FALSE, $rendered = TRUE) {
    $remote_field = $this->getRemoteFieldName();
    if (!$node->hasField($remote_field)) {
      return NULL;
    }

    $remote_source = $node->get($remote_field)->remote_source;
    $article_id = $node->get($remote_field)->article_id;

    if (!$remote_source || !$this->remoteSourceManager->hasDefinition($remote_source)) {
      return NULL;
    }

    /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceInterface $remote_source_instance */
    $remote_source_instance = $this->remoteSourceManager->createInstance($remote_source);
    if ($refresh) {
      $remote_source_instance->disableCache();
    }
    return $remote_source_instance->getArticle($article_id, $rendered);
  }

  /**
   * Create a local article node for the given remote article.
   *
   * @param \Drupal\ghi_content\RemoteContent\RemoteArticleInterface $article
   *   An article object from the remote source.
   * @param string $title
   *   An title for the article node.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The created article node if successful or NULL otherwise.
   */
  public function createNodeFromRemoteArticle(RemoteArticleInterface $article, $title) {
    $node = $this->loadNodeForRemoteContent($article);
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

    $status = $node->save();
    return $status == SAVED_NEW ? $node : NULL;
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
   * {@inheritdoc}
   */
  protected function getMigration(NodeInterface $node) {
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
   * {@inheritdoc}
   */
  public function updateNodeFromRemote(NodeInterface $node, $dry_run = FALSE, $reset = FALSE) {
    $remote_field = self::REMOTE_ARTICLE_FIELD;
    $article = $this->loadRemoteContentForNode($node, TRUE, FALSE);
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

    // Import the short title.
    $this->importManager->importTextfield($node, $article, $this->t('Short title'), 'getShortTitle', 'field_short_title');

    // Import the summary.
    $this->importManager->importTextfield($node, $article, $this->t('Summary'), 'getSummary', 'field_summary', 'html_text');

    // Import the image.
    $this->importManager->importImage($node, $article, 'field_image');

    // Import the paragraphs for the article.
    $this->importManager->importArticleParagraphs($node, $article, [], NULL, $cleanup);

    // Import the tags.
    $this->importManager->importTags($node, $article, 'field_tags');

    // Import the content space.
    $this->importManager->importContentSpace($node, $article, 'field_content_space');

    if ($node->isNew()) {
      $this->importManager->setupRelatedArticlesElement($node, $article);
    }

    if (!$dry_run) {
      $this->importManager->layoutManagerDiscardChanges($node, NULL);
      $node->setSyncing(TRUE);
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
    $local_data['paragraphs'] = $this->importManager->getLocalArticleParagraphUuids($original_node);

    // Then get the remote data by pretending to do an update on the node.
    $updated_node = clone $original_node;
    $this->updateNodeFromRemote($updated_node, TRUE);
    $article = $this->loadRemoteContentForNode($original_node, TRUE);

    $remote_data = $this->normalizeContentNodeData($updated_node);
    $remote_data['paragraphs'] = $this->importManager->getRemoteArticleParagraphUuids($article);

    // Calculate the checksums and compare.
    $local_checksum = md5(str_replace('"', '', json_encode($local_data)));
    $remote_checksum = md5(str_replace('"', '', json_encode($remote_data)));
    return $local_checksum === $remote_checksum;
  }

}
