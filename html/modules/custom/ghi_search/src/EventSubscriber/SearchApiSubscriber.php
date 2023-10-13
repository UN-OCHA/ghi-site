<?php

namespace Drupal\ghi_search\EventSubscriber;

use Drupal\search_api_solr\Event\PostCreateIndexDocumentEvent;
use Drupal\search_api_solr\Event\SearchApiSolrEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Search API events subscriber.
 */
class SearchApiSubscriber implements EventSubscriberInterface {

  /**
   * Adds the mapping to treat some Solr special fields as fulltext in views.
   *
   * @param \Drupal\search_api_solr\Event\PostCreateIndexDocumentEvent $event
   *   The Search API event.
   */
  public function onPostCreateIndexDocument(PostCreateIndexDocumentEvent $event) {
    // This can be used to alter the document before it gets send to solr.
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Workaround to avoid a fatal error during site install in some cases.
    // @see https://www.drupal.org/project/facets/issues/3199156
    if (!class_exists('\Drupal\search_api_solr\Event\SearchApiSolrEvents', TRUE)) {
      return [];
    }

    return [
      SearchApiSolrEvents::POST_CREATE_INDEX_DOCUMENT => 'onPostCreateIndexDocument',
    ];

  }

}
