<?php

namespace Drupal\ghi_subpages\Plugin\Block;

use Drupal\Core\Block\BlockBase;

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
    return ghi_subpages_get_top_navigation($node);
  }

}
