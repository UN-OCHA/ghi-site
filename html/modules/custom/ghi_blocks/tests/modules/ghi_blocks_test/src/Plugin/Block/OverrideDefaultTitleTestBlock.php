<?php

namespace Drupal\ghi_blocks_test\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\hpc_common\Plugin\HPCBlockMetadata;

/**
 * Provides a test block with an override default title.
 */
#[Block(
  id: 'ghi_blocks_override_default_title_test',
  admin_label: new TranslatableMarkup('Override default title test'),
  category: new TranslatableMarkup('GHI Blocks Test'),
)]
class OverrideDefaultTitleTestBlock extends GHIBlockBase implements OverrideDefaultTitleBlockInterface {

  /**
   * {@inheritdoc}
   */
  public static function metadata(): ?HPCBlockMetadata {
    return new HPCBlockMetadata(defaultTitle: 'Default override title');
  }

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
  protected function hasReliableIsEmpty(): bool {
    return TRUE;
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
