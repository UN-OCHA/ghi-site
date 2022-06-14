<?php

namespace Drupal\ghi_subpages\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Block\BlockBase;
use Drupal\ghi_subpages\Helpers\SubpageHelper;

/**
 * Provides a 'SubpageTitle' block.
 *
 * @Block(
 *  id = "subpage_title",
 *  admin_label = @Translation("Subpage title"),
 *  category = @Translation("Subpage"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *  }
 * )
 */
class SubpageTitle extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $contexts = $this->getContexts();
    if (empty($contexts['node']) || !$contexts['node']->getContextValue()) {
      return NULL;
    }
    /** @var \Drupal\node\NodeInterface $node */
    $node = $contexts['node']->getContextValue();
    if (SubpageHelper::isSubpageTypeNode($node)) {
      return [
        '#markup' => new FormattableMarkup('<h2>@title</h2>', [
          '@title' => $node->getTitle(),
        ]),
      ];
    }
    elseif (SubpageHelper::isBaseTypeNode($node) && SubpageHelper::getSectionOverviewLabel($node)) {
      return [
        '#markup' => new FormattableMarkup('<h2>@title</h2>', [
          '@title' => SubpageHelper::getSectionOverviewLabel($node),
        ]),
      ];
    }
  }

}
