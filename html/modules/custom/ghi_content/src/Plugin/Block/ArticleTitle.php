<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_content\Traits\ContentPathTrait;

/**
 * Provides a 'ArticleTitle' block.
 *
 * @Block(
 *  id = "article_title",
 *  admin_label = @Translation("Article title"),
 *  category = @Translation("Page"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *  }
 * )
 */
class ArticleTitle extends BlockBase {

  use ContentPathTrait;

  /**
   * {@inheritdoc}
   */
  public function build() {
    $contexts = $this->getContexts();
    if (empty($contexts['node']) || !$contexts['node']->getContextValue()) {
      return NULL;
    }

    $document = $this->getCurrentDocumentNode();

    /** @var \Drupal\node\NodeInterface $node */
    $node = $contexts['node']->getContextValue();
    if (!$node || !$node instanceof Article || !$document) {
      return NULL;
    }

    $title = $node->getTitle();
    $build = [
      '#full_width' => TRUE,
      '#cache' => [
        'tags' => $node->getCacheTags(),
        'contexts' => $node->getCacheContexts(),
      ],
      'title' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'article-title-wrapper',
            'content-width',
          ],
        ],
      ],
    ];

    if ($chapter = $node->getDocumentChapter($document)) {
      $build['title'][] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $document->toLink($document->label())->toString() . ' > ' . $chapter->getTitle(),
      ];
    }

    $build['title'][] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $title,
    ];

    return $build;
  }

}
