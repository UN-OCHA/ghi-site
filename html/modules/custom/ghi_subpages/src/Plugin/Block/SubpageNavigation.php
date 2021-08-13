<?php

namespace Drupal\ghi_subpages\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\ghi_subpages\Helpers\SubpageHelper;

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
class SubpageNavigation extends BlockBase {

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

    if ($base_entity->bundle() != 'section') {
      return;
    }

    // Always output parent link.
    $overview_link = $base_entity->toLink(t('Overview'))->toRenderable();
    if ($node->id() == $base_entity->id()) {
      $overview_link['#attributes']['class'][] = 'active';
    }

    $tabs = [
      0 => $overview_link + [
        'children' => [],
      ],
    ];

    foreach (SubpageHelper::SUPPORTED_SUBPAGE_TYPES as $subpage_type) {
      $matching_subpages = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
        'type' => $subpage_type,
        'field_entity_reference' => $base_entity->id(),
      ]);
      if (empty($matching_subpages)) {
        continue;
      }

      /** @var \Drupal\node\NodeInterface $subpage */
      $subpage = reset($matching_subpages);
      if (!$subpage->access('view')) {
        continue;
      }
      $cache_tags = array_merge($cache_tags, $subpage->getCacheTags());
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

}
