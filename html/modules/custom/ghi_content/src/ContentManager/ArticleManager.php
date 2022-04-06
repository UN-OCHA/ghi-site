<?php

namespace Drupal\ghi_content\ContentManager;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\ghi_content\RemoteContent\RemoteArticleInterface;
use Drupal\ghi_sections\SectionManager;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Article manager service class.
 */
class ArticleManager extends BaseContentManager {

  /**
   * Default mode for new directories. See self::chmod().
   */
  const THUMBNAIL_DIRECTORY = 'public://thumbnails/article';

  /**
   * The machine name of the bundle to use for articles.
   */
  const ARTICLE_BUNDLE = 'article';

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
    $node->field_remote_article = [
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
      'field_remote_article.remote_source' => $article->getSource()->getPluginId(),
      'field_remote_article.article_id' => $article->getId(),
    ]);
    return $results && !empty($results) ? reset($results) : NULL;
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
    if ($section->bundle() != 'section') {
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
      $previews[$article->id()] = $this->renderer->render($this->entityTypeManager->getViewBuilder('node')->view($article, $view_mode));
    }
    return $previews;
  }

}
