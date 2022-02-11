<?php

namespace Drupal\ghi_content\ContentManager;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Article manager service class.
 */
class ArticleManager extends BaseContentManager {

  /**
   * Load an article node by remote source and id.
   *
   * @param \Drupal\ghi_content\RemoteSource\RemoteSourceInterface $source
   *   The remote source that the article should come from.
   * @param object $article
   *   An article object from that remote source.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The article node if found or NULL.
   */
  public function loadNodeBySourceAndId(RemoteSourceInterface $source, $article) {
    $results = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'article',
      'field_remote_article.remote_source' => $source->getPluginId(),
      'field_remote_article.article_id' => $article->id,
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
   *
   * @return \Drupal\Core\Entity\EntityInterface[]|null
   *   An array of entity objects indexed by their ids.
   */
  public function loadNodesForTags(array $tags = NULL, NodeInterface $node = NULL, $op = 'AND') {
    if (empty($tags) && $node === NULL) {
      return NULL;
    }

    $tag_field = 'field_tags';

    // Setup the base query.
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->condition('status', TRUE);
    $query->condition('type', 'article');

    // For the logic behing the following conditions on tags see comments on
    // https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Entity!Query!QueryInterface.php/function/QueryInterface%3A%3AandConditionGroup/8.2.x
    if ($node) {
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
   * @return \Drupal\Core\Entity\EntityInterface[]|null
   *   An array of entity objects indexed by their ids.
   */
  public function loadNodesForSection(NodeInterface $section) {
    if ($section->bundle() != 'section') {
      return NULL;
    }
    return $this->loadNodesForTags(NULL, $section, 'AND');
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
   * Load minor tags for a section.
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
    $tags = [];
    foreach ($nodes as $node) {
      $tags = $tags + $this->getTags($node);
    }
    return array_unique($section_tags + $tags);
  }

  /**
   * Load minor tags for a section.
   *
   * @param \Drupal\node\NodeInterface $section
   *   The section.
   *
   * @return array
   *   An array with term ids as keys and term labels as values.
   */
  public function getNodeIdsGroupedByTag(NodeInterface $section) {
    if ($section->bundle() != 'section') {
      return NULL;
    }
    $nodes = $this->loadNodesForSection($section);
    $section_tags = $this->getTags($section);
    $tags = [];
    foreach ($nodes as $node) {
      foreach (array_unique($section_tags + $this->getTags($node)) as $id => $tag) {
        $tags[$id][$node->id()] = $node->id();
      }
    }
    return $tags;
  }

}
