<?php

namespace Drupal\ghi_blocks\Plugin\Block\MailingList;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Subscribe' block.
 *
 * @Block(
 *  id = "mailing_list_subscribe",
 *  admin_label = @Translation("Subscribe to mailing list"),
 *  category = @Translation("Mailing list")
 * )
 */
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
