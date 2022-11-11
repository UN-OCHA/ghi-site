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
    $title = NULL;
    if (SubpageHelper::isSubpageTypeNode($node)) {
      $title = $node->getTitle();
    }
    elseif (SubpageHelper::isBaseTypeNode($node) && SubpageHelper::getSectionOverviewLabel($node)) {
      $title = SubpageHelper::getSectionOverviewLabel($node);
    }
    if ($title) {
      return [
        '#markup' => new FormattableMarkup('<h2 class="content-width">@title</h2>', [
          '@title' => $title,
        ]),
        '#full_width' => TRUE,
      ];
    }
  }

}
