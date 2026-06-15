<?php

namespace Drupal\ghi_content\Context;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Builds contextual URLs for content rendered inside another content node.
 *
 * Content nodes keep their standalone canonical aliases, but some render
 * contexts intentionally nest those aliases under a section or document alias.
 * This service owns that URL composition so entity classes do not need to know
 * how Drupal's outbound path processing preserves a contextual path.
 */
class ContentUrlBuilder {

  /**
   * Build a URL for content inside the given context.
   *
   * @param \Drupal\Core\Url $content_url
   *   The standalone content URL, built with a relative alias.
   * @param \Drupal\node\NodeInterface|null $context_node
   *   The optional context node whose alias should prefix the content URL.
   *
   * @return \Drupal\Core\Url
   *   The contextual URL, or the original content URL when no context exists.
   */
  public function build(Url $content_url, ?NodeInterface $context_node = NULL): Url {
    if (!$context_node) {
      return $content_url;
    }

    $context_path = $context_node->toUrl()->toString();
    $content_path = $content_url->toString();
    $path = $context_path . $content_path;
    $url = Url::fromUserInput($path);
    // Mark the composed path as already aliased. Otherwise Drupal's path alias
    // manager can collapse it back to the standalone node alias.
    $url->setOption('alias', TRUE);
    // The outbound path processor uses this to preserve the contextual path
    // during link sanitization.
    $url->setOption('custom_path', $path);
    return $url;
  }

}
