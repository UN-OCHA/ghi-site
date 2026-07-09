<?php

namespace Drupal\ghi_blocks_test\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_blocks\Interfaces\OptionalTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;

/**
 * Provides a test block with an optional title.
 */
#[Block(
  id: 'ghi_blocks_optional_title_test',
  admin_label: new TranslatableMarkup('Optional title test'),
  category: new TranslatableMarkup('GHI Blocks Test'),
)]
class OptionalTitleTestBlock extends GHIBlockBase implements OptionalTitleBlockInterface {

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    return [
      '#markup' => $this->getBlockConfig()['markup'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfigurationDefaults() {
    return [
      'markup' => 'Test content',
    ];
  }

}
