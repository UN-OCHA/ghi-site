<?php

namespace Drupal\ghi_sections\MenuItemType;

use Drupal\Core\Cache\Cache;

/**
 * A class for section mega menus.
 */
class SectionMegaMenu extends SectionMenuWidgetBase {

  /**
   * The label for the mega menu component.
   *
   * @var \Drupal\Component\Render\MarkupInterface|string
   */
  private $label;

  /**
   * The list of nodes for the mega menu.
   *
   * @var array
   */
  private $nodes;

  /**
   * The header for the mega menu component as a render array.
   *
   * @var array
   */
  private $header;

  /**
   * Whether this mega menu is currently active.
   *
   * @var bool
   */
  private $isActive = FALSE;

  /**
   * Construct a mega menu widget.
   */
  public function __construct($label, array $nodes, $header) {
    $this->label = $label;
    $this->nodes = $nodes;
    $this->header = $header;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * Get the nodes to list in the mega menu.
   *
   * @return array
   *   An associative array of nodes, keys are the group labels.
   */
  private function getNodes() {
    return $this->nodes;
  }

  /**
   * Get the nodes to list in the dropdown.
   *
   * @return array
   *   Get the header as a render array if available.
   */
  private function getHeader() {
    return $this->header;
  }

  /**
   * Set this menu to be active.
   */
  public function setActive() {
    $this->isActive = TRUE;
  }

  /**
   * Check if this menu is active.
   *
   * @return bool
   *   The new status for this menu.
   */
  public function isActive() {
    return $this->isActive;
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderable() {
    $current_node = $this->getCurrentNode();
    $wrapper_attributes = ['class' => ['megamenu']];
    if ($this->isActive()) {
      $wrapper_attributes['class'][] = 'active';
    }

    $build = [
      '#type' => 'container',
      '#attributes' => $wrapper_attributes,
      'label' => [
        '#markup' => $this->getLabel(),
      ],
    ];

    if ($header = $this->getHeader()) {
      $build['header'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['megamenu-header']],
        'header' => $header,
      ];
    }

    $groups = [];
    $groups_cache_tags = [];
    foreach ($this->getNodes() as $group => $nodes) {
      $links = [];
      $group_cache_tags = [];
      foreach ($nodes as $node) {
        /** @var \Drupal\node\NodeInterface $node */
        // Check if the page should be visible.
        if (!$node->access('view')) {
          continue;
        }

        // Build the link render array.
        $link = $node->toLink(NULL, 'canonical', ['fragment' => 'page-title'])->toRenderable();
        $group_cache_tags = Cache::mergeTags($group_cache_tags, $node->getCacheTags());
        if (!$node->isPublished()) {
          $link['#attributes']['class'][] = 'node--unpublished';
        }

        if ($current_node && $current_node->toUrl() == $node->toUrl()) {
          $link['#attributes']['class'][] = 'active';
          $build['#attributes']['class'][] = 'active';
        }
        $links[] = $link;
      }
      $groups[] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'megamenu-group-wrapper',
          ],
        ],
        [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['megamenu-group'],
          ],
          [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => $group,
          ],
          [
            '#theme' => 'item_list',
            '#items' => $links,
            '#gin_lb_theme_suggestions' => FALSE,
          ],
        ],
        '#cache' => [
          'tags' => $group_cache_tags,
        ],
      ];
      $groups_cache_tags = Cache::mergeTags($groups_cache_tags, $group_cache_tags);
    }
    if (empty($build)) {
      return NULL;
    }
    $build += [
      'item_list' => [
        '#theme' => 'item_list',
        '#items' => $groups,
        '#gin_lb_theme_suggestions' => FALSE,
      ],
      '#cache' => [
        'tags' => $groups_cache_tags,
      ],
    ];
    return $build;
  }

}
