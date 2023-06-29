<?php

namespace Drupal\ghi_sections\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\ghi_sections\Entity\GlobalSection;
use Drupal\ghi_sections\Menu\SectionDropdown;
use Drupal\ghi_sections\Menu\SectionMegaMenu;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'SectionNavigation' block.
 *
 * @Block(
 *  id = "section_navigation",
 *  admin_label = @Translation("Section navigation"),
 *  category = @Translation("Menus"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *  }
 * )
 */
class SectionNavigation extends BlockBase implements ContainerFactoryPluginInterface {

  use LayoutEntityHelperTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The section manager.
   *
   * @var \Drupal\ghi_sections\SectionManager
   */
  protected $sectionManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\ghi_sections\Plugin\Block\SectionNavigation $instance */
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->sectionManager = $container->get('ghi_sections.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $contexts = $this->getContexts();

    if (empty($contexts['node']) || !$contexts['node']->getContextValue()) {
      return [];
    }
    $node = $contexts['node']->getContextValue();

    if (!$node || !$node instanceof NodeInterface) {
      return NULL;
    }
    if ($node && $node instanceof GlobalSection) {
      // Don't show the section navigation on nodes of type global section.
      return NULL;
    }

    $section = $this->sectionManager->getCurrentSection($node);
    if (!$section) {
      return NULL;
    }

    $output = [
      '#cache' => [
        'tags' => $this->getCacheTags(),
      ],
    ];
    $cache_tags = $node->getCacheTags();

    // Always output parent link.
    $overview_link = $section->toLink($this->t('Overview'), 'canonical', ['fragment' => 'page-title'])->toRenderable();
    if ($node->id() == $section->id()) {
      $overview_link['#attributes']['class'][] = 'active';
      $overview_link['#wrapper_attributes']['class'][] = 'active';
    }

    $tabs = [
      0 => $overview_link,
    ];

    // Collect all subpages of this section that should appear in the
    // navigation.
    $subpages = [];
    $callable = function (callable $hook, string $module) use (&$subpages, $section) {
      $subpages = array_merge($subpages, $hook($section));
    };
    $this->moduleHandler->invokeAllWith('section_standard_subpage_nodes', $callable);
    $this->moduleHandler->invokeAllWith('section_subpage_nodes', $callable);

    // And add these subpages to the navigation tabs.
    foreach ($subpages as $subpage) {
      if ($subpage instanceof SectionDropdown) {
        $drop_down_links = $this->buildDropDown($subpage, $node, $cache_tags);
        if (empty($drop_down_links)) {
          continue;
        }
        $tabs[] = $drop_down_links;
      }
      elseif ($subpage instanceof SectionMegaMenu) {
        $drop_down_links = $this->buildMegaMenu($subpage, $node, $cache_tags);
        if (empty($drop_down_links)) {
          continue;
        }
        $tabs[] = $drop_down_links;
      }
      elseif ($subpage instanceof NodeInterface) {
        $cache_tags = array_merge($cache_tags, $subpage->getCacheTags());
        if (!$subpage->access('view') || (!$this->subpageHasContent($subpage) && !$subpage->access('update'))) {
          continue;
        }
        $link = $subpage->toLink(NULL, 'canonical', ['fragment' => 'page-title'])->toRenderable();
        if ($node->id() == $subpage->id()) {
          $link['#attributes']['class'][] = 'active';
          $link['#wrapper_attributes']['class'][] = 'active';
        }
        $tabs[] = $link;
      }
    }

    foreach ($tabs as $tab) {
      $meta_data = BubbleableMetadata::createFromRenderArray($tab);
      $cache_tags = Cache::mergeTags($cache_tags, $meta_data->getCacheTags());
    }

    $output['entity_navigation'] = [
      '#theme' => 'item_list',
      '#items' => $tabs,
      '#attributes' => [
        'class' => [
          'links--entity-navigation',
        ],
      ],
      '#cache' => [
        'tags' => $cache_tags,
      ],
      '#gin_lb_theme_suggestions' => FALSE,
      // This is important to make the template suggestions logic work in
      // common_design_subtheme.theme.
      '#context' => [
        'plugin_id' => $this->getPluginId(),
      ],
    ];

    $output['#cache']['contexts'] = ['url.path'];
    $output['#cache']['tags'] = Cache::mergeTags($output['#cache']['tags'], $cache_tags);
    return $output;
  }

  /**
   * Build the drop down for multi-level navigation items.
   *
   * @param \Drupal\ghi_sections\Menu\SectionDropdown $dropdown
   *   The dropdown object.
   * @param \Drupal\node\NodeInterface $current_node
   *   The node representing the current page.
   * @param array $cache_tags
   *   An array of cache tags.
   *
   * @return array
   *   A render array suitable to include in an item list.
   */
  private function buildDropDown(SectionDropdown $dropdown, $current_node, &$cache_tags) {
    $wrapper_attributes = ['class' => ['dropdown']];
    $child_cache_tags = [];
    $links = [];

    if ($header_link = $dropdown->getHeaderLink()) {
      $link = $header_link->toRenderable();
      $link['#attributes']['class'][] = 'header-link';
      if ($link['#url'] == $current_node->toUrl()) {
        $link['#attributes']['class'][] = 'active';
        $wrapper_attributes['class'][] = 'active';
      }
      $links[] = $link;
    }

    foreach ($dropdown->getNodes() as $node) {
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
      if ($current_node->toUrl() == $node->toUrl()) {
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
        '#markup' => $dropdown->getLabel(),
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

  /**
   * Build the mega menu for multi-level navigation items.
   *
   * @param \Drupal\ghi_sections\Menu\SectionMegaMenu $megamenu
   *   The mega menu object.
   * @param \Drupal\node\NodeInterface $current_node
   *   The node representing the current page.
   * @param array $cache_tags
   *   An array of cache tags.
   *
   * @return array
   *   A render array suitable to include in an item list.
   */
  private function buildMegaMenu(SectionMegaMenu $megamenu, $current_node, &$cache_tags) {
    $wrapper_attributes = ['class' => ['megamenu']];
    if ($megamenu->isActive()) {
      $wrapper_attributes['class'][] = 'active';
    }

    $groups_cache_tags = [];
    $build = [
      '#type' => 'container',
      '#attributes' => $wrapper_attributes,
      'label' => [
        '#markup' => $megamenu->getLabel(),
      ],
    ];

    if ($header = $megamenu->getHeader()) {
      $build['header'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['megamenu-header']],
        'header' => $header,
      ];
    }

    $groups = [];
    foreach ($megamenu->getNodes() as $group => $nodes) {
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

        if ($current_node->toUrl() == $node->toUrl()) {
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
    $cache_tags = Cache::mergeTags($cache_tags, $groups_cache_tags);
    return $build;
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
    return !empty($sections[0]->getComponents());
  }

}
