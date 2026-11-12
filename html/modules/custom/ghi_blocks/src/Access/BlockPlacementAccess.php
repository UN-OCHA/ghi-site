<?php

namespace Drupal\ghi_blocks\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\ghi_blocks\LayoutBuilder\AllowedBlockDefinitions;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Prevents direct add URLs from bypassing placement restrictions.
 */
class BlockPlacementAccess {

  /**
   * Checks whether the requested block can be added to the layout.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The destination storage.
   * @param string $plugin_id
   *   The block plugin ID.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public static function access(SectionStorageInterface $section_storage, string $plugin_id): AccessResultInterface {
    $definitions = \Drupal::classResolver(AllowedBlockDefinitions::class)->getDefinitions($section_storage);
    return AccessResult::allowedIf(isset($definitions[$plugin_id]))->setCacheMaxAge(0);
  }

}
