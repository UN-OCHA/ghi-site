<?php

namespace Drupal\ghi_sections\MenuItemType;

use Drupal\Core\Cache\Cache;

/**
 * A class for section mega menus.
 */
class SectionMegaMenu extends SectionMenuWidgetBase {

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
   * The configuration for the widget.
   *
   * @var array
   */
  private $configuration;

  /**
   * Whether this mega menu is currently active.
   *
   * @var bool
   */
  private $isActive = FALSE;

  /**
   * Construct a mega menu widget.
   */
  public function __construct($label, array $nodes, $header, $configuration) {
    $this->label = $label;
    $this->nodes = $nodes;
    $this->header = $header;
    $this->configuration = $configuration;
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
   *
   * @param bool $state
   *   The new status for this menu.
   */
  public function setActive($state = FALSE) {
    $this->isActive = $state;
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
    $columns = $this->configuration['mega_menu_columns'] ?? 4;

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'megamenu',
          'megamenu--' . $columns,
        ],
      ],
      'label' => [
        '#markup' => $this->getLabel(),
      ],
    ];

    if ($this->isProtected()) {
      $build['#attributes']['class'][] = 'protected';
    }

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
    if ($this->isActive()) {
      $build['#attributes']['class'][] = 'active';
    }
    $build += [
      'item_list' => [
        '#theme' => 'item_list',
        '#items' => $groups,
        '#gin_lb_theme_suggestions' => FALSE,
      ],
      '#cache' => [
        'tags' => Cache::mergeTags($groups_cache_tags, $this->getCacheTags()),
      ],
    ];
    return $build;
  }

}
