<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\ghi_content\RemoteContent\RemoteContentInterface;
use Drupal\ghi_content\RemoteContent\RemoteContentItemBaseInterface;

/**
 * Identifies content items using block configuration and a supplied snapshot.
 *
 * Implementations must not load remote content when resolving these references.
 */
interface RemoteContentItemBlockInterface {

  /**
   * Check whether this block references the supplied article or document.
   *
   * @param \Drupal\ghi_content\RemoteContent\RemoteContentInterface $content
   *   The article or document snapshot.
   *
   * @return bool
   *   TRUE when the source and parent content ID match, even if the item was
   *   removed from the snapshot.
   */
  public function referencesRemoteContent(RemoteContentInterface $content): bool;

  /**
   * Resolve the configured item from the supplied article or document.
   *
   * @param \Drupal\ghi_content\RemoteContent\RemoteContentInterface $content
   *   The article or document snapshot.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteContentItemBaseInterface|null
   *   The item, or NULL if the parent does not match or the item is absent.
   */
  public function getRemoteContentItem(RemoteContentInterface $content): ?RemoteContentItemBaseInterface;

}
