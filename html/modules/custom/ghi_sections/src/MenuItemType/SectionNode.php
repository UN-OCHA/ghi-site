<?php

namespace Drupal\ghi_sections\MenuItemType;

use Drupal\Core\Cache\Cache;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\node\NodeInterface;

/**
 * A class for section dropdowns.
 */
class SectionNode extends SectionMenuWidgetBase {

  use LayoutEntityHelperTrait;

  /**
   * The node to display in the widget.
   *
   * @var \Drupal\node\NodeInterface
   */
  private $node;

  /**
   * Construct a section menu item object.
   *
   * @param string $label
   *   The label.
   * @param \Drupal\node\NodeInterface $node
   *   The subpage node object.
   */
  public function __construct($label, NodeInterface $node) {
    $this->label = $label;
    $this->node = $node;
  }

  /**
   * Get the node.
   *
   * @return \Drupal\node\NodeInterface
   *   The node object.
   */
  private function getNode() {
    return $this->node;
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderable() {
    $subpage = $this->getNode();
    if (!$subpage->access('view') || (!$this->subpageHasContent($subpage) && !$subpage->access('update'))) {
      return NULL;
    }
    $link = $subpage->toLink($this->getLabel(), 'canonical', ['fragment' => 'page-title'])->toRenderable();
    if ($this->getCurrentNode()->id() == $subpage->id()) {
      $link['#attributes']['class'][] = 'active';
      $link['#wrapper_attributes']['class'][] = 'active';
    }
    $link['#cache']['tags'] = Cache::mergeTags($link['#cache']['tags'] ?? [], $subpage->getCacheTags());
    return $link;
  }

  /**
   * Check if the given subpage has configured content already.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The subpage node to check.
   *
   * @return bool
   *   TRUE if there is content, FALSE otherwhise.
   */
  private function subpageHasContent(NodeInterface $node) {
    $section_storage = $this->getSectionStorageForEntity($node);
    if (!$section_storage) {
      return FALSE;
    }
    $sections = $section_storage->getSections();
    return !empty($sections[0]?->getComponents());
  }

}
