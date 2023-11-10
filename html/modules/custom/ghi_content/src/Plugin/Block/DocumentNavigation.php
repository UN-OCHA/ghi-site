<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\ghi_content\Entity\ContentBase;
use Drupal\ghi_content\Entity\Document;
use Drupal\ghi_content\Traits\ContentPathTrait;

/**
 * Provides a 'DocumentNavigation' block.
 *
 * @Block(
 *  id = "document_navigation",
 *  admin_label = @Translation("Document navigation"),
 *  category = @Translation("Menus"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *  }
 * )
 */
class DocumentNavigation extends BlockBase {

  use ContentPathTrait;

  /**
   * {@inheritdoc}
   */
  public function build() {
    $contexts = $this->getContexts();

    if (empty($contexts['node']) || !$contexts['node']->getContextValue()) {
      return [];
    }
    $node = $contexts['node']->getContextValue();
    if (!$node || !$node instanceof ContentBase || $this->getCurrentSectionNode()) {
      // Only show this on nodes of type content base and if we are not on a
      // section page.
      return NULL;
    }

    $document = $node instanceof Document ? $node : $node->getContextNode();
    if (!$document || !$document instanceof Document) {
      // Only show this if there is a document context.
      return NULL;
    }

    $output = [
      '#cache' => [
        'tags' => $this->getCacheTags(),
      ],
    ];
    $cache_tags = $document->getCacheTags();

    // Always output parent link.
    $overview_link = $document->toLink($this->t('Overview'), 'canonical', ['fragment' => 'page-title'])->toRenderable();
    if ($node->id() == $document->id()) {
      $overview_link['#attributes']['class'][] = 'active';
      $overview_link['#wrapper_attributes']['class'][] = 'active';
    }

    $tabs = [
      0 => $overview_link,
    ];

    foreach ($document->getChapters() as $chapter) {
      $articles = $document->getChapterArticles($chapter);
      $drop_down_links = $this->buildDropDown($chapter->getShortTitle(), $articles, $node, $cache_tags);
      if (empty($drop_down_links)) {
        continue;
      }
      $tabs[] = $drop_down_links;
    }

    foreach ($tabs as $tab) {
      $meta_data = BubbleableMetadata::createFromRenderArray($tab);
      $cache_tags = Cache::mergeTags($cache_tags, $meta_data->getCacheTags());
    }

    $output['entity_navigation'] = [
      '#theme' => 'item_list',
      '#items' => $tabs,
      '#cache' => [
        'tags' => $cache_tags,
      ],
      '#gin_lb_theme_suggestions' => FALSE,
      // This is important to make the template suggestions logic work in
      // common_design_subtheme.theme.
      '#context' => [
        'plugin_type' => 'entity_navigation',
        'plugin_id' => $this->getPluginId(),
      ],
    ];

    $output['#cache']['contexts'] = ['url.path'];
    $output['#cache']['tags'] = Cache::mergeTags($output['#cache']['tags'], $cache_tags);
    return $output;
  }

  /**
   * Build the drop down links for multi-level navigation items.
   *
   * @param \Drupal\Component\Render\MarkupInterface|string $label
   *   The label to use for the dropdown component.
   * @param \Drupal\node\NodeInterface[] $nodes
   *   A list of nodes to display in the dropdown.
   * @param \Drupal\node\NodeInterface $current_node
   *   The node representing the current page.
   * @param array $cache_tags
   *   An array of cache tags.
   *
   * @return array
   *   A render array suitable to include in an item list.
   */
  private function buildDropDown($label, $nodes, $current_node, &$cache_tags) {
    $wrapper_attributes = ['class' => ['dropdown']];
    $child_cache_tags = [];
    $links = [];
    foreach ($nodes as $node) {
      // Check if the page should be visible.
      if (!$node->access('view')) {
        continue;
      }

      // Build the link render array.
      $link = $node->toLink(NULL, 'canonical', ['fragment' => 'page-title'])->toRenderable();
      $child_cache_tags = Cache::mergeTags($child_cache_tags, $node->getCacheTags());

      if (!$node->isPublished()) {
        $link['#attributes']['class'][] = 'node--unpublished';
      }
      if ($current_node->id() == $node->id()) {
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
        '#markup' => $label,
      ],
      'item_list' => [
        '#theme' => 'item_list',
        '#items' => $links,
        '#gin_lb_theme_suggestions' => FALSE,
      ],
      '#cache' => [
        'tags' => $child_cache_tags,
      ],
    ];
    $cache_tags = Cache::mergeTags($cache_tags, $child_cache_tags);
    return $links;
  }

}
