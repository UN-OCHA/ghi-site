<?php

namespace Drupal\ghi_subpages\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * Constructs an EntityActionBase object.
   *
   * @param mixed[] $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $contexts = $this->getContexts();
    if (empty($contexts['node']) || !$contexts['node']->getContextValue()) {
      return NULL;
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

    if (!SubpageHelper::isBaseTypeNode($base_entity)) {
      return;
    }

    // Always output parent link.
    $overview_link = $base_entity->toLink($this->t('Overview'))->toRenderable();
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
