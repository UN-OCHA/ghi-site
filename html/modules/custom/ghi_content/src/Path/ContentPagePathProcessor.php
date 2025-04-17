<?php

namespace Drupal\ghi_content\Path;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\ghi_content\Traits\ContentPathTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A path processor class for content pages.
 *
 * The logic in this class allows for node aliases to be nested inside other
 * aliases, creating duplicated content from the perspective of search engines.
 * That's a trade-off we are ok with, because this allows to have documents
 * associated to sections and articles associated to documents, while still
 * providing standalone pages for articles and documents and providing
 * consistent in-page navigation for these objects.
 */
class ContentPagePathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface, EventSubscriberInterface {

  use ContentPathTrait;

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    $original_path = $path;
    if (strpos($path, '/article/') > 0) {
      $path = $this->processArticleUrl($path);
    }
    elseif (strpos($path, '/document/') > 0) {
      $path = $this->processDocumentUrl($path);
    }
    if ($original_path != $path) {
      self::disableRouteNormalizer(TRUE);
    }
    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    if (!empty($options['custom_path'])) {
      $path = $options['custom_path'];
    }
    return $path;
  }

  /**
   * Process an article url.
   *
   * @param string $path
   *   The path to process.
   */
  private function processArticleUrl($path) {
    $section = $this->getSectionNodeFromPath($path);
    $document = $this->getDocumentNodeFromPath($path);
    $article = $this->getArticleNodeFromPath($path);

    if (!$article) {
      return $path;
    }

    if (strpos($path, '/document/') > 0 && !$document) {
      return $path;
    }

    // This is a request for an article inside a document. We need to find the
    // document based on the alias, confirm that the article is actually part
    // of the document.
    if ($document && !$document->hasArticle($article)) {
      return $path;
    }

    if (strpos($path, '/article/') !== 0 && !$section && !$document) {
      return $path;
    }

    if ($section && !$article->isPartOfSection($section)) {
      return $path;
    }

    $path = '/node/' . $article->id();
    return $path;
  }

  /**
   * Process a document url.
   *
   * @param string $path
   *   The path to process.
   */
  private function processDocumentUrl($path) {
    $section = $this->getSectionNodeFromPath($path);
    $document = $this->getDocumentNodeFromPath($path);
    if (!$document) {
      return $path;
    }

    if (!$document && strpos($path, '/document/') !== 0 && !$section) {
      return $path;
    }

    // This is a request for a document inside a section. We need to find the
    // section based on the alias, confirm that the document is associated to
    // that section and that the current user has access to the section.
    if ($section && $document && !$document->isPartOfSection($section)) {
      return $path;
    }

    if (!$document->access('view') || !$section?->access('view')) {
      return $path;
    }

    $path = '/node/' . $document->id();
    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Register our event right before the redirect module redirects.
    $events[KernelEvents::REQUEST][] = ['disableRedirectOnContextualPath', 31];
    return $events;
  }

  /**
   * Disable a redirect on contextial paths.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event to process.
   */
  public static function disableRedirectOnContextualPath(RequestEvent $event) {
    if (self::disableRouteNormalizer()) {
      $event->getRequest()->attributes->set('_disable_route_normalizer', TRUE);
    }
  }

  /**
   * Disable the route normalizer to prevent unintended redirects.
   *
   * @param bool $status
   *   TRUE to disable the route normalizer, FALSE to enable it.
   *
   * @return bool|void
   *   If not void, a boolean indicating whether the route normalizer is
   *   disabled.
   */
  public static function disableRouteNormalizer($status = NULL) {
    $disable_route_normalizer = &drupal_static(__FUNCTION__, FALSE);
    if ($status === NULL) {
      return $disable_route_normalizer;
    }
    $disable_route_normalizer = $status;
  }

}
