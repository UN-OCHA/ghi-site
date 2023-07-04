<?php

namespace Drupal\ghi_sections\MenuItemType;

use Drupal\Core\Cache\Cache;

/**
 * A class for section dropdowns.
 */
class SectionDropdown extends SectionMenuWidgetBase {

  /**
   * The label to be used for the widget.
   *
   * @var string
   */
  private $label;

  /**
   * The nodes to display in the widget.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  private $nodes;

  /**
   * An optional link to display as the first item in the widget.
   *
   * @var \Drupal\Core\Link
   */
  private $headerLink;

  /**
   * Construct a section menu item object.
   *
   * @param string $label
   *   The label for the widget.
   * @param \Drupal\node\NodeInterface[] $nodes
   *   The node objects to display.
   * @param \Drupal\Core\Link $header_link
   *   An optional header link.
   */
  public function __construct($label, array $nodes, $header_link = NULL) {
    $this->label = $label;
    $this->nodes = $nodes;
    $this->headerLink = $header_link;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * Get the nodes to list in the dropdown.
   *
   * @return \Drupal\node\NodeInterface[]
   *   An array of nodes.
   */
  private function getNodes() {
    return $this->nodes;
  }

  /**
   * Get the nodes to list in the dropdown.
   *
   * @return \Drupal\Core\Link
   *   Get the header link if available.
   */
  private function getHeaderLink() {
    return $this->headerLink;
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderable() {
    $wrapper_attributes = ['class' => ['dropdown']];
    $cache_tags = [];
    $links = [];
    $current_node = $this->getCurrentNode();

    if ($header_link = $this->getHeaderLink()) {
      $link = $header_link->toRenderable();
      $link['#attributes']['class'][] = 'header-link';
      if ($current_node && $link['#url'] == $current_node->toUrl()) {
        $link['#attributes']['class'][] = 'active';
        $wrapper_attributes['class'][] = 'active';
      }
      $links[] = $link;
    }

    foreach ($this->getNodes() as $node) {
      // Check if the page should be visible.
      if (!$node->access('view')) {
        continue;
      }

      // Build the link render array.
      $link = $node->toLink(NULL, 'canonical', ['fragment' => 'page-title'])->toRenderable();
      $cache_tags = Cache::mergeTags($cache_tags, $node->getCacheTags());

      if (!$node->isPublished()) {
        $link['#attributes']['class'][] = 'node--unpublished';
      }
      if ($current_node && $current_node->toUrl() == $node->toUrl()) {
        $link['#attributes']['class'][] = 'active';
        $wrapper_attributes['class'][] = 'active';
      }
      $links[] = $link;
    }
    if (empty($links)) {
      return NULL;
    }
    $links = [
      '#type' => 'container',
      '#attributes' => $wrapper_attributes,
      'label' => [
        '#markup' => $this->getLabel(),
      ],
      'item_list' => [
        '#theme' => 'item_list',
        '#items' => $links,
        '#gin_lb_theme_suggestions' => FALSE,
      ],
      '#cache' => [
        'tags' => $cache_tags,
      ],
    ];
    return $links;
  }

}
