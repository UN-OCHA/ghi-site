<?php

namespace Drupal\ghi_blocks\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Drupal\node\NodeInterface;

/**
 * Controller for page elements.
 */
class PageElementsController extends ControllerBase {

  use LayoutEntityHelperTrait;

  /**
   * Access callback for the page elements backend page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(NodeInterface $node) {
    if (!$this->isLayoutCompatibleEntity($node)) {
      // We allow access only for entities using layout builder.
      return AccessResult::forbidden();
    }
    $section_storage = $this->getSectionStorageForEntity($node);
    if (!$section_storage instanceof OverridesSectionStorageInterface) {
      // We allow access only for entities that have their layout customized.
      return AccessResult::forbidden();
    }
    // Check if the current user has update rights on the base node.
    return $node->access('update', NULL, TRUE);
  }

  /**
   * The _title_callback for the page that renders the admin form.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The current node.
   *
   * @return string
   *   The page title.
   */
  public function title(NodeInterface $node) {
    return $this->t('Page elements overview for @type %label', [
      '@type' => $node->type->entity->label(),
      '%label' => $node->label(),
    ]);
  }

}
