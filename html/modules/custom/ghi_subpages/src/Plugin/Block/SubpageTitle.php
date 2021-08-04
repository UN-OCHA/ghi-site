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
    $node = $contexts['node']->getContextValue();
    if (in_array($node->getType(), SubpageHelper::SUPPORTED_SUBPAGE_TYPES)) {
      return [
        '#markup' => new FormattableMarkup('<h2>@title</h2>', [
          '@title' => $node->getTitle(),
        ]),
      ];
    }
    elseif (in_array($node->getType(), SubpageHelper::SUPPORTED_BASE_TYPES)) {
      return [
        '#markup' => new FormattableMarkup('<h2>@title</h2>', [
          '@title' => $this->t('Overview'),
        ]),
      ];
    }
  }

}
