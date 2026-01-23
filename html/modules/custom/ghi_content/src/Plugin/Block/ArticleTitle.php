<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_content\Traits\ContentPathTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'ArticleTitle' block.
 */
#[Block(
  id: 'article_title',
  admin_label: new TranslatableMarkup('Article title'),
  category: new TranslatableMarkup('Page'),
  context_definitions: [
    'node' => new EntityContextDefinition('entity:node', new TranslatableMarkup('Node')),
  ]
)]
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
    $section = $this->getCurrentSectionNode();

    /** @var \Drupal\node\NodeInterface $node */
    $node = $contexts['node']->getContextValue();
    if (!$node || !$node instanceof Article) {
      return NULL;
    }

    if (!$document && !$section) {
      return NULL;
    }

    $title = $node->getTitle();
    $build = [
      '#full_width' => TRUE,
      '#cache' => [
        'tags' => $node->getCacheTags(),
        'contexts' => $node->getCacheContexts(),
      ],
      '#attributes' => [
        'class' => ['subpage-title-block'],
      ],
      'title' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'subpage-title-wrapper',
            'content-width',
          ],
        ],
      ],
    ];

    // If we have a section context, we also want to add breadcrumbs.
    if ($section && $document) {
      // For single chapter documents, we don't show the chapter title in the
      // breadcrumb.
      $single_chapter_document = count($document->getChapters(FALSE)) == 1;
      if ($chapter = $node->getDocumentChapter($document)) {
        $title_args = [
          '@document' => $document->toLink($document->label())->toString(),
          '@chapter' => $chapter->getTitle(),
        ];
        $title_prefix = new FormattableMarkup('<span class="document-link">@document</span>', $title_args);
        if (!$single_chapter_document && !$chapter->isHidden()) {
          $title_prefix .= new FormattableMarkup(' / <span class="chapter">@chapter</span>', $title_args);
        }
        $build['title'][] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $title_prefix,
        ];
        $build['title']['#attributes']['class'][] = 'has-title-prefix';
      }
    }

    $build['title'][] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $title,
    ];

    return $build;
  }

}
