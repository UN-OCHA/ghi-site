<?php

namespace Drupal\ghi_subpages\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Block\BlockBase;
use Drupal\ghi_sections\Entity\GlobalSection;
use Drupal\ghi_subpages\Helpers\SubpageHelper;
use Drupal\ghi_subpages\SubpageTrait;
use Drupal\node\NodeInterface;

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

  use SubpageTrait;

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
    if (!$node || !$node instanceof NodeInterface) {
      return NULL;
    }
    if ($node && $node instanceof GlobalSection) {
      // Don't show the subpage title on nodes of type global section.
      return NULL;
    }

    // Get parent if needed.
    $base_entity = $this->getBaseTypeNode($node);
    if (!$base_entity || !SubpageHelper::isBaseTypeNode($base_entity) || !$base_entity->id()) {
      // Don't show the subpage title if no parent section is available.
      return NULL;
    }

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
