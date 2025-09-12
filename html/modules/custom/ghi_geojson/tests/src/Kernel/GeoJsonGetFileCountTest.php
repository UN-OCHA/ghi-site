<?php

namespace Drupal\Tests\ghi_geojson\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\ghi_geojson\GeoJson;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for the GeoJson::getFileCount method.
 *
 * @coversDefaultClass \Drupal\ghi_geojson\GeoJson
 * @group ghi_geojson
 */
class GeoJsonGetFileCountTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ghi_geojson',
    'file',
    'system',
    'user',
  ];

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * GeoJSON service.
   *
   * @var \Drupal\ghi_geojson\GeoJson
   */
  protected $geoJsonService;

  /**
   * Test directory path.
   *
   * @var string
   */
  protected $testDirectory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('system', ['sequences']);
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');

    $this->fileSystem = $this->container->get('file_system');
    $this->geoJsonService = $this->container->get('geojson');

    $this->testDirectory = 'public://test_file_count';

    // Create test directory structure.
    $this->createTestDirectoryStructure();
  }

  /**
   * Creates a test directory structure with various files.
   */
  protected function createTestDirectoryStructure(): void {
    // Create main test directory.
    $this->fileSystem->prepareDirectory($this->testDirectory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // Create test files with different names and extensions.
    $test_files = [
      'file1.geojson',
      'file2.geojson',
      'file3.min.geojson',
      'temp_file.geojson',
      'backup_file.bak',
      'regular_file.txt',
      'another.json',
    ];

    foreach ($test_files as $file) {
      $filepath = $this->testDirectory . '/' . $file;
      file_put_contents($filepath, 'test content');
    }

    // Create subdirectories (these count as files in scanDirectory).
    $subdirectories = [
      'subdir1',
      'temp_dir',
      'backup_dir',
    ];

    foreach ($subdirectories as $subdir) {
      $subdir_path = $this->testDirectory . '/' . $subdir;
      $this->fileSystem->prepareDirectory($subdir_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

      // Add a file in each subdirectory.
      file_put_contents($subdir_path . '/subfile.txt', 'sub content');
    }

    // Create an empty directory for testing.
    $empty_dir = 'public://test_empty_directory';
    $this->fileSystem->prepareDirectory($empty_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  }

  /**
   * Tests file count for non-existent directory.
   *
   * @covers ::getFileCount
   */
  public function testGetFileCountNonExistentDirectory(): void {
    $non_existent_dir = 'public://does_not_exist';
    $result = $this->geoJsonService->getFileCount($non_existent_dir);

    $this->assertEquals(0, $result, 'Should return 0 for non-existent directory.');
  }

  /**
   * Tests file count for empty directory.
   *
   * @covers ::getFileCount
   */
  public function testGetFileCountEmptyDirectory(): void {
    $empty_dir = 'public://test_empty_directory';
    $result = $this->geoJsonService->getFileCount($empty_dir);

    $this->assertEquals(0, $result, 'Should return 0 for empty directory.');
  }

  /**
   * Tests file count for directory with files and subdirectories.
   *
   * @covers ::getFileCount
   */
  public function testGetFileCountWithFiles(): void {
    $result = $this->geoJsonService->getFileCount($this->testDirectory);

    // Expected count: 7 files + 3 subdirectories = 10 total items.
    $this->assertEquals(10, $result, 'Should return correct count of files and directories.');
  }

  /**
   * Tests file count with exclude filter for specific strings.
   *
   * @covers ::getFileCount
   */
  public function testGetFileCountWithExcludeFilter(): void {
    // Exclude files containing 'temp'.
    $exclude = ['temp'];
    $result = $this->geoJsonService->getFileCount($this->testDirectory, $exclude);

    // Expected count: 10 total - 2 items with 'temp' (temp_file.geojson, temp_dir) = 8.
    $this->assertEquals(8, $result, 'Should exclude files containing "temp".');
  }

  /**
   * Tests file count with multiple exclude filters.
   *
   * @covers ::getFileCount
   */
  public function testGetFileCountWithMultipleExcludes(): void {
    // Exclude files containing 'temp' or 'backup'.
    $exclude = ['temp', 'backup'];
    $result = $this->geoJsonService->getFileCount($this->testDirectory, $exclude);

    // Expected count: 10 total - 4 items with 'temp' or 'backup' = 6.
    // (temp_file.geojson, temp_dir, backup_file.bak, backup_dir).
    $this->assertEquals(6, $result, 'Should exclude files containing "temp" or "backup".');
  }

  /**
   * Tests file count with exclude filter that matches no files.
   *
   * @covers ::getFileCount
   */
  public function testGetFileCountWithNonMatchingExclude(): void {
    // Exclude files containing 'nonexistent'.
    $exclude = ['nonexistent'];
    $result = $this->geoJsonService->getFileCount($this->testDirectory, $exclude);

    // Expected count: all 10 items since none contain 'nonexistent'.
    $this->assertEquals(10, $result, 'Should return full count when exclude filter matches nothing.');
  }

  /**
   * Tests file count with exclude filter that excludes all files.
   *
   * @covers ::getFileCount
   */
  public function testGetFileCountExcludeAll(): void {
    // Exclude files containing 'file' or 'dir' - should match most/all items.
    $exclude = ['file', 'dir'];
    $result = $this->geoJsonService->getFileCount($this->testDirectory, $exclude);

    // Expected count: files that don't contain 'file' or 'dir'.
    // Only 'another.json' should remain.
    $this->assertEquals(1, $result, 'Should return minimal count when most files are excluded.');
  }

  /**
   * Tests file count with empty exclude array.
   *
   * @covers ::getFileCount
   */
  public function testGetFileCountWithEmptyExclude(): void {
    $exclude = [];
    $result = $this->geoJsonService->getFileCount($this->testDirectory, $exclude);

    // Empty exclude array should behave the same as no exclude filter.
    $this->assertEquals(10, $result, 'Should return full count with empty exclude array.');
  }

  /**
   * Tests file count with null exclude parameter.
   *
   * @covers ::getFileCount
   */
  public function testGetFileCountWithNullExclude(): void {
    $result = $this->geoJsonService->getFileCount($this->testDirectory, NULL);

    // NULL exclude should return all files.
    $this->assertEquals(10, $result, 'Should return full count with NULL exclude parameter.');
  }

  /**
   * Tests file count with case sensitivity in exclude filter.
   *
   * @covers ::getFileCount
   */
  public function testGetFileCountExcludeCaseSensitive(): void {
    // Create a file with uppercase name for case sensitivity test.
    $uppercase_file = $this->testDirectory . '/TEMP_FILE.txt';
    file_put_contents($uppercase_file, 'test content');

    // Exclude files containing lowercase 'temp'.
    $exclude = ['temp'];
    $result = $this->geoJsonService->getFileCount($this->testDirectory, $exclude);

    // Expected count: 11 total - 2 items with lowercase 'temp' = 9.
    // TEMP_FILE.txt should not be excluded because str_contains is case-sensitive.
    $this->assertEquals(9, $result, 'Exclude filter should be case-sensitive.');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Clean up test directories if they exist.
    try {
      $paths_to_clean = [
        $this->testDirectory,
        'public://test_empty_directory',
      ];

      foreach ($paths_to_clean as $path) {
        if ($this->fileSystem && is_dir($path)) {
          $this->fileSystem->deleteRecursive($path);
        }
      }
    } catch (\Exception $e) {
      // Ignore cleanup errors during tearDown.
    }

    parent::tearDown();
  }

}