<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\ghi_blocks\Update\RemovedDeployHookRegistry;
use Drupal\Tests\UnitTestCase;

/**
 * Tests that removed deploy hook names are not reused.
 *
 * @group ghi_blocks
 */
final class RemovedDeployHookRegistryTest extends UnitTestCase {

  /**
   * Tests the removed hook registry against currently defined deploy hooks.
   */
  public function testRemovedDeployHookNamesAreNotReused(): void {
    $current_hooks = $this->getCurrentDeployHooks();
    $reused_hooks = array_intersect(array_keys(RemovedDeployHookRegistry::HOOKS), $current_hooks);

    $this->assertSame([], $reused_hooks, 'Removed deploy hook names were reused: ' . implode(', ', $reused_hooks));
  }

  /**
   * Finds deploy hook functions in custom module deploy files.
   *
   * @return string[]
   *   The deploy hook function names.
   */
  private function getCurrentDeployHooks(): array {
    $custom_modules_path = dirname(__DIR__, 4);
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($custom_modules_path, \FilesystemIterator::SKIP_DOTS));
    $hooks = [];

    foreach ($files as $file) {
      if (!$file->isFile() || !str_ends_with($file->getFilename(), '.deploy.php')) {
        continue;
      }
      $tokens = token_get_all((string) file_get_contents($file->getPathname()));
      foreach ($tokens as $index => $token) {
        if (!is_array($token) || $token[0] !== T_FUNCTION) {
          continue;
        }
        for ($next = $index + 1, $count = count($tokens); $next < $count; $next++) {
          if (!is_array($tokens[$next])) {
            if ($tokens[$next] === '(') {
              break;
            }
            continue;
          }
          if ($tokens[$next][0] === T_STRING && preg_match('/^[a-z0-9_]+_deploy_[a-z0-9_]+$/', $tokens[$next][1])) {
            $hooks[] = $tokens[$next][1];
            break;
          }
        }
      }
    }

    sort($hooks);
    return $hooks;
  }

}
