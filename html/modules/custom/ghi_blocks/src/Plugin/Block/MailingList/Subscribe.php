<?php

namespace Drupal\ghi_blocks\Plugin\Block\MailingList;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'Subscribe' block.
 */
#[Block(
  id: 'mailing_list_subscribe',
  admin_label: new TranslatableMarkup('Subscribe to mailing list'),
  category: new TranslatableMarkup('Mailing list')
)]
class Subscribe extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'mailing_list_subscribe',
    ];
  }

}
