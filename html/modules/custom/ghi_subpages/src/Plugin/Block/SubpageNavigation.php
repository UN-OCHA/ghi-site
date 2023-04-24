<?php

namespace Drupal\ghi_subpages\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\ghi_sections\Entity\GlobalSection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ghi_subpages\Helpers\SubpageHelper;
use Drupal\ghi_subpages\SubpageManager;
use Drupal\ghi_subpages\SubpageTrait;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\node\NodeInterface;

/**
 * Provides a 'SubpageNavigation' block.
 *
 * @Block(
 *  id = "subpage_navigation",
 *  admin_label = @Translation("Subpage navigation"),
 *  category = @Translation("Menus"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *  }
 * )
 */
class SubpageNavigation extends BlockBase implements ContainerFactoryPluginInterface {

  use LayoutEntityHelperTrait;
  use SubpageTrait;

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\ghi_subpages\Plugin\Block\SubpageNavigation $instance */
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->moduleHandler = $container->get('module_handler');
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
      // Don't show the subpage navigation on nodes of type global section.
      return NULL;
    }

    $output = [
      '#cache' => [
        'tags' => $this->getCacheTags(),
      ],
    ];
    $cache_tags = $node->getCacheTags();

    // Get parent if needed.
    $base_entity = $this->getBaseTypeNode($node);
    if (!$base_entity || !SubpageHelper::isBaseTypeNode($base_entity) || !$base_entity->id()) {
      return [];
    }

    // Always output parent link.
    $overview_link = $base_entity->toLink($this->t('Overview'), 'canonical', ['fragment' => 'page-title'])->toRenderable();
    if ($node->id() == $base_entity->id()) {
      $overview_link['#attributes']['class'][] = 'active';
      $overview_link['#wrapper_attributes']['class'][] = 'active';
    }

    $tabs = [
      0 => $overview_link,
    ];

    // First iterate over the default subpages and add links if the user
    // current has access.
    foreach (SubpageManager::SUPPORTED_SUBPAGE_TYPES as $subpage_type) {
      $matching_subpages = $this->entityTypeManager->getStorage('node')->loadByProperties([
        'type' => $subpage_type,
        'field_entity_reference' => $base_entity->id(),
      ]);
      if (empty($matching_subpages)) {
        continue;
      }

      /** @var \Drupal\node\NodeInterface $subpage */
      $subpage = reset($matching_subpages);
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

    // Allow other modules to alter the links.
    $context = [
      'node' => $node,
      'base_entity' => $base_entity,
    ];
    $this->moduleHandler->alter('subpage_navigation_links', $tabs, $context, $cache_tags);
    if (empty($tabs)) {
      return NULL;
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

    $output['#cache']['tags'] = Cache::mergeTags($output['#cache']['tags'], $cache_tags);
    return $output;
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
