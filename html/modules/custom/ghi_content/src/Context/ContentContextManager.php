<?php

namespace Drupal\ghi_content\Context;

use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_content\Entity\ContentBase;
use Drupal\ghi_content\Entity\Document;
use Drupal\ghi_content\Traits\ContentPathTrait;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\node\NodeInterface;

/**
 * Resolves and validates presentation contexts for content nodes.
 *
 * A content node can be rendered as a standalone page or inside a section or
 * document. That presentation context affects URLs, page titles, metadata,
 * hero-image inheritance, navigation, and cache keys. This service owns the
 * context decision so those rules do not drift between entity methods, blocks,
 * and render preprocess hooks.
 */
class ContentContextManager {

  use ContentPathTrait;

  /**
   * Resolve the current route context for a content node.
   *
   * @param \Drupal\ghi_content\Entity\ContentBase $content
   *   The content node being rendered.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The current route context when valid for the content node.
   */
  public function resolveAmbientContext(ContentBase $content): ?NodeInterface {
    if ($content instanceof Article) {
      $document = $this->getCurrentDocumentNode();
      if ($document && $this->isValidContextNode($content, $document)) {
        return $document;
      }
    }

    $section = $this->getCurrentSectionNode();
    if ($section && $this->isValidContextNode($content, $section)) {
      return $section;
    }
    return NULL;
  }

  /**
   * Validate a candidate presentation context for a content node.
   *
   * @param \Drupal\ghi_content\Entity\ContentBase $content
   *   The content node being rendered.
   * @param \Drupal\node\NodeInterface $context_node
   *   The candidate context node.
   *
   * @return bool
   *   TRUE if the context is valid for the content node.
   */
  public function isValidContextNode(ContentBase $content, NodeInterface $context_node): bool {
    if ($context_node instanceof SectionNodeInterface) {
      return $content->isPartOfSection($context_node);
    }
    if ($content instanceof Article && $context_node instanceof Document) {
      return $context_node->hasArticle($content);
    }
    return FALSE;
  }

  /**
   * Get the page title for content in its current presentation context.
   *
   * @param \Drupal\ghi_content\Entity\ContentBase $content
   *   The content node being rendered.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The context title, or the content title when no context applies.
   */
  public function getPageTitle(ContentBase $content) {
    return $content->getContextNode()?->label() ?? $content->label();
  }

}
