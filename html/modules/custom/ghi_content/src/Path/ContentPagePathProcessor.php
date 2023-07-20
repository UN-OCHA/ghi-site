<?php

namespace Drupal\ghi_content\Path;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\ghi_content\ContentManager\DocumentManager;
use Drupal\ghi_content\Traits\ContentPathTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A path processor class for content pages.
 *
 * The logic in this class allows for node aliases to be nexted inside other
 * aliases, creating duplicated content from the perspective of search engines.
 * That's a trade-off we are ok with, because this allows to have documents
 * associated to sections and articles associated to documents, while still
 * providing standalone pages for articles and documents and providing
 * consistent in-page navigation for these objects.
 */
class ContentPagePathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  use ContentPathTrait;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The document manager.
   *
   * @var \Drupal\ghi_content\ContentManager\DocumentManager
   */
  protected $documentManager;

  /**
   * Constructs a document manager.
   */
  public function __construct(RequestStack $request_stack, DocumentManager $document_manager) {
    $this->requestStack = $request_stack;
    $this->documentManager = $document_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    if (strpos($path, '/article/') > 0) {
      $path = $this->processArticleUrl($path);
    }
    elseif (strpos($path, '/document/') > 0) {
      $path = $this->processDocumentUrl($path);
    }
    $this->requestStack->getCurrentRequest()->attributes->set('_disable_route_normalizer', TRUE);
    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], Request $request = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {
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
    // of the document and that the current user has access to the section.
    if ($document && (!$document->hasArticle($article) || !$document->access('view'))) {
      return $path;
    }

    if (strpos($path, '/article/') !== 0 && !$section && !$document) {
      return $path;
    }

    if ($section && !$section->access('view')) {
      return $path;
    }

    if (!$article->access('view')) {
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

    if (!$document->access('view') || !$section->access('view')) {
      return $path;
    }

    $path = '/node/' . $document->id();
    return $path;
  }

}
