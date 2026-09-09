<?php

namespace Drupal\Tests\hpc_api\Kernel;

use Drupal\KernelTests\Core\File\FileTestBase;

/**
 * Tests cleanup of disposable files without replacing their storage directory.
 *
 * @covers ::hpc_api_clear_directory_contents
 * @group hpc_api
 */
class ClearDirectoryContentsTest extends FileTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once DRUPAL_ROOT . '/modules/custom/hpc_api/hpc_api.module';
  }

  /**
   * Tests that cleanup preserves the directory and its permissions.
   */
  public function testPreservesDirectory(): void {
    $directory = 'public://cleanup';
    mkdir($directory);
    mkdir($directory . '/nested');
    file_put_contents($directory . '/boundary.geojson', '{}');
    file_put_contents($directory . '/.hidden', 'hidden');
    file_put_contents($directory . '/nested/import.json', '{}');

    $path = $this->container->get('file_system')->realpath($directory);
    chmod($path, 02770);
    clearstatcache(TRUE, $path);
    $before = stat($path);

    hpc_api_clear_directory_contents($directory);

    clearstatcache(TRUE, $path);
    $after = stat($path);
    $this->assertSame($before['ino'], $after['ino']);
    $this->assertSame($before['uid'], $after['uid']);
    $this->assertSame($before['gid'], $after['gid']);
    $this->assertSame($before['mode'], $after['mode']);
    $this->assertSame(['.', '..'], scandir($path));

    // Repeated cache rebuilds must also preserve an already empty directory.
    hpc_api_clear_directory_contents($directory);
    clearstatcache(TRUE, $path);
    $this->assertSame($before['mode'], fileperms($path));
  }

  /**
   * Tests that a missing directory is still created.
   */
  public function testCreatesMissingDirectory(): void {
    $directory = 'public://missing';

    hpc_api_clear_directory_contents($directory);

    $this->assertDirectoryExists($directory);
    $this->assertTrue(is_writable($directory));
  }

  /**
   * Tests that NFS placeholders are left for the filesystem to clean up.
   */
  public function testPreservesNfsPlaceholders(): void {
    $directory = 'public://cleanup';
    mkdir($directory);
    $placeholder = $directory . '/.nfs000000000000000000000001';
    file_put_contents($placeholder, 'open file');
    file_put_contents($directory . '/boundary.geojson', '{}');

    hpc_api_clear_directory_contents($directory);

    $this->assertSame('open file', file_get_contents($placeholder));
    $this->assertFileDoesNotExist($directory . '/boundary.geojson');

    // Another rebuild must succeed while the placeholder remains present.
    hpc_api_clear_directory_contents($directory);
    $this->assertFileExists($placeholder);
  }

  /**
   * Tests that nested symlinks do not delete files outside the directory.
   */
  public function testPreservesSymlinkTargets(): void {
    $directory = 'public://cleanup';
    $outside = 'public://outside';
    mkdir($directory);
    mkdir($outside);
    file_put_contents($outside . '/keep.json', '{}');
    $file_system = $this->container->get('file_system');
    $path = $file_system->realpath($directory);
    symlink($file_system->realpath($outside), $path . '/linked');
    symlink($path . '/missing', $path . '/broken');

    hpc_api_clear_directory_contents($directory);

    $this->assertFileExists($outside . '/keep.json');
    $this->assertSame(['.', '..'], scandir($path));
  }

}
