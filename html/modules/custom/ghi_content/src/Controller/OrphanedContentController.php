<?php

namespace Drupal\ghi_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\ContentManager\DocumentManager;
use Drupal\node\NodeTypeInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Controller for orphaned content.
 */
class OrphanedContentController extends ControllerBase {

  /**
   * The machine name of the view to use for this article list.
   */
  const VIEW_NAME = 'orphaned_content';

  /**
   * The machine name of the views display to use for this article list.
   */
  const VIEW_DISPLAY = 'block_orphaned_content';

  const FIELD_NAME = 'field_orphaned';

  /**
   * Get the page title for an orphaned content page.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node type to check for orphans.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page title.
   */
  public function listPageTitle(NodeTypeInterface $node_type) {
    $type_map = [
      ArticleManager::ARTICLE_BUNDLE => $this->t('Articles'),
      DocumentManager::DOCUMENT_BUNDLE => $this->t('Documents'),
    ];
    $label = $type_map[$node_type->id()] ?? $this->t('Content');
    return $this->t('Orphaned @type', [
      '@type' => strtolower($label),
    ]);
  }

  /**
   * Page callback for the orphaned content list admin page.
   *
   * @return array|null
   *   A render array.
   */
  public function listOrphanedContent() {
    $view = Views::getView(self::VIEW_NAME);

    $build = [];
    $build[] = [
      '#markup' => '<p>' . $this->t('This page lists all content pages that are no longer available on the remote system where the content originated.') . '</p>',
    ];

    if (!$view instanceof ViewExecutable || !$view->getDisplay(self::VIEW_DISPLAY)) {
      // Log the problem and add a message.
      $this->getLogger('views')->error('View @view or display @display not found.', [
        '@view' => self::VIEW_NAME,
        '@display' => self::VIEW_DISPLAY,
      ]);
      $this->messenger()->addError($this->t('There was a technical problem creating this content list. The issue has been logged and we will be looking at it as soon as possible.'));
      return $build;
    }
    $build[] = [
      '#type' => 'view',
      '#name' => self::VIEW_NAME,
      '#display_id' => self::VIEW_DISPLAY,
      '#embed' => TRUE,
    ];
    return $build;
  }

}
