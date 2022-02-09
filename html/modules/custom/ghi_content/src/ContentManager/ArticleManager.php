<?php

namespace Drupal\ghi_content\ContentManager;

use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;

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

}
