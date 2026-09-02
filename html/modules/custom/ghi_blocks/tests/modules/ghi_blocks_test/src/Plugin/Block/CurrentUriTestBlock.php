<?php

namespace Drupal\ghi_blocks_test\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;

/**
 * Provides a test block that renders the current URI.
 */
#[Block(
  id: 'ghi_blocks_current_uri_test',
  admin_label: new TranslatableMarkup('Current URI test'),
  category: new TranslatableMarkup('GHI Blocks Test'),
)]
class CurrentUriTestBlock extends GHIBlockBase {

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    return [
      '#markup' => $this->getCurrentUri(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfigurationDefaults() {
    return [];
  }

}
