<?php

namespace Drupal\Tests\hpc_security\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests local asset URL normalization for CSP hashes.
 */
#[Group('hpc_security')]
class AssetHashTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    require_once __DIR__ . '/../../../hpc_security.module';
  }

  /**
   * Tests hashes without relying on Drupal's old query-string state value.
   */
  #[DataProvider('providerAssetUrls')]
  public function testHashFromUrl(string $url, bool $local_file): void {
    $working_directory = getcwd();
    $had_base_url = array_key_exists('base_url', $GLOBALS);
    $base_url = $GLOBALS['base_url'] ?? NULL;
    try {
      $GLOBALS['base_url'] = 'https://example.com';
      chdir(__DIR__ . '/../../fixtures');
      $expected = $local_file ? 'sha256-' . base64_encode(hash_file('sha256', 'asset.js', TRUE)) : NULL;
      $this->assertSame($expected, hpc_security_get_hash_from_url($url));
    }
    finally {
      chdir($working_directory);
      if ($had_base_url) {
        $GLOBALS['base_url'] = $base_url;
      }
      else {
        unset($GLOBALS['base_url']);
      }
    }
  }

  /**
   * Provides local, versioned, external and missing asset URLs.
   */
  public static function providerAssetUrls(): array {
    return [
      'relative' => ['asset.js', TRUE],
      'root relative' => ['/asset.js', TRUE],
      'legacy query string' => ['asset.js?old-cache-key', TRUE],
      'versioned' => ['asset.js?v=1.2.3', TRUE],
      'query and fragment' => ['asset.js?v=1.2.3#fragment', TRUE],
      'same origin' => ['https://example.com/asset.js?v=1.2.3', TRUE],
      'external' => ['https://external.example/asset.js?v=1.2.3', FALSE],
      'missing' => ['missing.js?v=1.2.3', FALSE],
      'empty' => ['', FALSE],
    ];
  }

}
