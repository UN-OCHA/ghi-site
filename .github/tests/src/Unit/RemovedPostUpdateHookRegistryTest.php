<?php

namespace Drupal\Tests\ghi\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Tests that post-update names converted to deploy hooks are not reused.
 *
 * These hooks were replaced by equivalent deploy hooks before the currently
 * supported releases. Existing databases therefore record the deploy hook
 * names, not these post-update names. Declaring them through
 * hook_removed_post_updates() would incorrectly block those databases from
 * updating, so this project-level registry reserves the names instead.
 *
 * @group ghi
 */
final class RemovedPostUpdateHookRegistryTest extends UnitTestCase {

  /**
   * Removed hook names keyed by the date on which they were replaced.
   */
  private const REMOVED_HOOKS = [
    'ghi_menu_post_update_adjust_admin_menu' => '2023-07-25',
    'ghi_subpages_post_update_create_standard_subpages' => '2023-07-21',
    'ghi_subpages_post_update_queue_logframes' => '2023-07-21',
    'ghi_subpages_post_update_update_subpage_url_aliases' => '2023-07-21',
    'ghi_subpages_custom_post_update_move_existing_content' => '2023-07-25',
  ];

  /**
   * Tests that the historical names do not occur in current post-update files.
   */
  public function testRemovedPostUpdateHookNamesAreNotReused(): void {
    $custom_modules_path = dirname(__DIR__, 4) . '/html/modules/custom';
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($custom_modules_path, \FilesystemIterator::SKIP_DOTS));
    $reused_hooks = [];

    foreach ($files as $file) {
      if (!$file->isFile() || !str_ends_with($file->getFilename(), '.post_update.php')) {
        continue;
      }
      $contents = (string) file_get_contents($file->getPathname());
      foreach (array_keys(self::REMOVED_HOOKS) as $hook) {
        if (str_contains($contents, $hook)) {
          $reused_hooks[] = $hook;
        }
      }
    }

    $this->assertSame([], array_values(array_unique($reused_hooks)), 'Removed post-update hook names were reused: ' . implode(', ', $reused_hooks));
  }

}
