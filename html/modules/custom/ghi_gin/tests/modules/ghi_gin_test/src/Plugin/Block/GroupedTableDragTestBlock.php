<?php

namespace Drupal\ghi_gin_test\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanHeadlineFigures;

/**
 * Reuses the real Key figures editor without requiring remote plan data.
 */
#[Block(
  id: 'ghi_gin_grouped_tabledrag_test',
  admin_label: new TranslatableMarkup('Grouped key figures test'),
)]
class GroupedTableDragTestBlock extends PlanHeadlineFigures {

  /**
   * {@inheritdoc}
   */
  public function getBlockContext() {
    return [];
  }

}
