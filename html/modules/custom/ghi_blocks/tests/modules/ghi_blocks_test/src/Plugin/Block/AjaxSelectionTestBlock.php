<?php

namespace Drupal\ghi_blocks_test\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;

/**
 * Provides a test block that renders the submitted AJAX selection.
 */
#[Block(
  id: 'ghi_blocks_ajax_selection_test',
  admin_label: new TranslatableMarkup('AJAX selection test'),
  category: new TranslatableMarkup('GHI Blocks Test'),
)]
class AjaxSelectionTestBlock extends GHIBlockBase {

  /**
   * The number of times content was built instead of read from cache.
   *
   * @var int
   */
  public int $buildCount = 0;

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $this->buildCount++;
    return [
      '#markup' => $this->requestStack->getCurrentRequest()->request->get('entity_id'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfigurationDefaults() {
    return [];
  }

}
