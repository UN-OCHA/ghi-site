<?php

namespace Drupal\ghi_sections\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\ghi_sections\Entity\GlobalSection;
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
   * The section menu storage.
   *
   * @var \Drupal\ghi_sections\Menu\SectionMenuStorage
   */
  protected $sectionMenuStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\ghi_sections\Plugin\Block\SectionNavigation $instance */
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->sectionManager = $container->get('ghi_sections.manager');
    $instance->sectionMenuStorage = $container->get('ghi_sections.section_menu.storage');
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

    // Collect all (stored or default) menu items of this section.
    $menu_items = $this->sectionMenuStorage->getSectionMenuItems();

    // And add these subpages to the navigation tabs.
    foreach ($menu_items->getAll() as $menu_item) {
      if (!$menu_item->getPlugin()->isValid() || !$menu_item->getPlugin()->getStatus()) {
        continue;
      }
      $widget = $menu_item->getPlugin()->getWidget();
      if (!$widget) {
        continue;
      }
      $widget->setCurrentNode($node);
      $tabs[] = $widget->toRenderable();
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

}
