<?php

namespace Drupal\ghi_subpages\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ghi_subpages\Helpers\SubpageHelper;
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

    $output = [];
    $cache_tags = [];

    // Get parent if needed.
    /** @var \Drupal\node\NodeInterface $base_entity */
    $base_entity = $node;
    if ($node->hasField('field_entity_reference')) {
      /** @var \Drupal\node\NodeInterface $base_entity */
      $base_entity = $node->field_entity_reference->entity;
    }

    if (!SubpageHelper::isBaseTypeNode($base_entity) || !$base_entity->id()) {
      return [];
    }

    // Always output parent link.
    $overview_link = $base_entity->toLink(SubpageHelper::getSectionOverviewLabel($base_entity))->toRenderable();
    if ($node->id() == $base_entity->id()) {
      $overview_link['#attributes']['class'][] = 'active';
    }

    $tabs = [
      0 => $overview_link + [
        'children' => [],
      ],
    ];

    foreach (SubpageHelper::SUPPORTED_SUBPAGE_TYPES as $subpage_type) {
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
        $tabs[0]['children'][] = [
          '#markup' => $subpage->getTitle(),
          '#wrapper_attributes' => [
            'class' => ['disabled'],
          ],
        ];
        continue;
      }
      $link = $subpage->toLink(NULL)->toRenderable();
      if ($node->id() == $subpage->id()) {
        $link['#attributes']['class'][] = 'active';
      }
      $tabs[0]['children'][] = $link;
    }

    // Allow other modules to alter the links.
    $context = [
      'node' => $node,
      'base_entity' => $base_entity,
    ];
    $this->moduleHandler->alter('subpage_navigation_links', $tabs, $context);

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
    ];

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
