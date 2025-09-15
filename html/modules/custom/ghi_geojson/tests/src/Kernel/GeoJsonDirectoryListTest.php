<?php

namespace Drupal\Tests\ghi_geojson\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\ghi_geojson\GeoJsonDirectoryList;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for the GeoJsonDirectoryList service.
 *
 * @coversDefaultClass \Drupal\ghi_geojson\GeoJsonDirectoryList
 * @group ghi_geojson
 */
class GeoJsonDirectoryListTest extends KernelTestBase {

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
   * GeoJSON directory list service.
   *
   * @var \Drupal\ghi_geojson\GeoJsonDirectoryList
   */
  protected $geoJsonDirectoryList;

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
    $this->geoJsonDirectoryList = $this->container->get('geojson.directory_list');

    $this->testDirectory = 'public://test_directory_list';

    // Create test directory structure.
    $this->createTestDirectoryStructure();
  }

  /**
   * Creates a test directory structure with various files and subdirectories.
   */
  protected function createTestDirectoryStructure(): void {
    // Create main test directory.
    $this->fileSystem->prepareDirectory($this->testDirectory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // Create test files with different types.
    $test_files = [
      'country.geojson' => '{"type": "FeatureCollection", "features": []}',
      'admin1.geojson' => '{"type": "FeatureCollection", "features": [{"type": "Feature"}]}',
      'admin2.geojson' => '{"type": "FeatureCollection", "features": []}',
      'cities.min.geojson' => '{"type":"FeatureCollection","features":[]}',
      'regular_file.txt' => 'not geojson',
    ];

    foreach ($test_files as $filename => $content) {
      $filepath = $this->testDirectory . '/' . $filename;
      file_put_contents($filepath, $content);
    }

    // Create minified version for one of the geojson files.
    $minified_content = '{"type":"FeatureCollection","features":[]}';
    file_put_contents($this->testDirectory . '/country.min.geojson', $minified_content);

    // Create subdirectories with files.
    $subdir1_path = $this->testDirectory . '/regions';
    $this->fileSystem->prepareDirectory($subdir1_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    file_put_contents($subdir1_path . '/region1.geojson', '{"type": "FeatureCollection", "features": []}');
    file_put_contents($subdir1_path . '/region2.geojson', '{"type": "FeatureCollection", "features": []}');

    $subdir2_path = $this->testDirectory . '/boundaries';
    $this->fileSystem->prepareDirectory($subdir2_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    file_put_contents($subdir2_path . '/boundary1.geojson', '{"type": "FeatureCollection", "features": []}');

    // Create an empty subdirectory.
    $empty_subdir_path = $this->testDirectory . '/empty_subdir';
    $this->fileSystem->prepareDirectory($empty_subdir_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // Create separate empty directory for empty directory tests.
    $empty_dir = 'public://test_empty_directory';
    $this->fileSystem->prepareDirectory($empty_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  }

  /**
   * Tests building directory listing with files.
   *
   * @covers ::buildDirectoryListing
   * @covers ::buildFileItems
   * @covers ::buildFileLink
   */
  public function testBuildDirectoryListingWithFiles(): void {
    $result = $this->geoJsonDirectoryList->buildDirectoryListing($this->testDirectory);

    $this->assertArrayHasKey('#theme', $result);
    $this->assertEquals('item_list', $result['#theme']);
    $this->assertArrayHasKey('#items', $result);
    $this->assertArrayHasKey('#attributes', $result);
    $this->assertEquals(['geojson-directory-listing'], $result['#attributes']['class']);
    $this->assertArrayHasKey('#attached', $result);
    $this->assertEquals(['ghi_geojson/geojson_admin'], $result['#attached']['library']);

    // Check that we have items in the list.
    $this->assertNotEmpty($result['#items']);

    // Find a file item and verify it has link structure.
    $file_found = FALSE;
    foreach ($result['#items'] as $item) {
      // Look for a file item (should be an array with multiple elements for file + minified version).
      if (is_array($item) && isset($item[0]['#type']) && $item[0]['#type'] === 'link') {
        $file_found = TRUE;
        $this->assertEquals('link', $item[0]['#type']);
        $this->assertArrayHasKey('#title', $item[0]);
        $this->assertArrayHasKey('#url', $item[0]);
        break;
      }
    }
    $this->assertTrue($file_found, 'Should find at least one file link in the directory listing.');
  }

  /**
   * Tests building directory listing with subdirectories.
   *
   * @covers ::buildDirectoryListing
   */
  public function testBuildDirectoryListingWithSubdirectories(): void {
    $result = $this->geoJsonDirectoryList->buildDirectoryListing($this->testDirectory);

    // Find subdirectory items.
    $subdir_found = FALSE;
    $empty_subdir_found = FALSE;

    foreach ($result['#items'] as $item) {
      // Look for directory items (should have #markup and children).
      if (is_array($item) && isset($item['#markup'])) {
        $markup = $item['#markup'];
        if (strpos($markup, 'regions') !== FALSE && strpos($markup, '2 files') !== FALSE) {
          $subdir_found = TRUE;
          $this->assertArrayHasKey('children', $item);
          $this->assertCount(2, $item['children']);
          $this->assertArrayHasKey('#wrapper_attributes', $item);
          $this->assertEquals(['directory'], $item['#wrapper_attributes']['class']);
        }
        if (strpos($markup, 'empty_subdir') !== FALSE && strpos($markup, '0 files') !== FALSE) {
          $empty_subdir_found = TRUE;
          $this->assertArrayHasKey('children', $item);
          $this->assertEmpty($item['children']);
        }
      }
    }

    $this->assertTrue($subdir_found, 'Should find subdirectory with files in the listing.');
    $this->assertTrue($empty_subdir_found, 'Should find empty subdirectory in the listing.');
  }

  /**
   * Tests building directory listing with minified files.
   *
   * @covers ::buildDirectoryListing
   * @covers ::buildFileItems
   */
  public function testBuildDirectoryListingWithMinifiedFiles(): void {
    $result = $this->geoJsonDirectoryList->buildDirectoryListing($this->testDirectory);

    // Look for a file that has a minified version.
    $minified_found = FALSE;
    foreach ($result['#items'] as $item) {
      if (is_array($item) && count($item) >= 3) {
        // Should have: original file link, separator, minified file link.
        if (isset($item[0]['#title']) && $item[0]['#title'] === 'country.geojson' &&
            isset($item[1]['#markup']) && $item[1]['#markup'] === '&nbsp;/&nbsp;' &&
            isset($item[2]['#title']) && $item[2]['#title'] === 'country.min.geojson') {
          $minified_found = TRUE;
          $this->assertEquals('link', $item[0]['#type']);
          $this->assertEquals('link', $item[2]['#type']);
          break;
        }
      }
    }

    $this->assertTrue($minified_found, 'Should find file with minified version linked properly.');
  }

  /**
   * Tests building listing without links.
   *
   * @covers ::buildDirectoryListing
   * @covers ::buildFileItems
   * @covers ::buildFileItem
   */
  public function testBuildListingWithoutLinks(): void {
    $result = $this->geoJsonDirectoryList->buildDirectoryListing($this->testDirectory, FALSE);

    $this->assertArrayHasKey('#items', $result);
    $this->assertNotEmpty($result['#items']);

    // Find a file item and verify it has markup instead of link.
    $markup_found = FALSE;
    foreach ($result['#items'] as $item) {
      if (is_array($item) && isset($item[0]['#markup'])) {
        $markup_found = TRUE;
        $this->assertArrayNotHasKey('#type', $item[0]);
        $this->assertArrayHasKey('#markup', $item[0]);
        // Should not have link properties.
        $this->assertArrayNotHasKey('#url', $item[0]);
        break;
      }
    }

    $this->assertTrue($markup_found, 'Should find file items as markup when links are disabled.');
  }

  /**
   * Tests empty directory listing.
   *
   * @covers ::buildDirectoryListing
   */
  public function testEmptyDirectoryListing(): void {
    $empty_dir = 'public://test_empty_directory';
    $result = $this->geoJsonDirectoryList->buildDirectoryListing($empty_dir);

    $this->assertArrayHasKey('#theme', $result);
    $this->assertEquals('item_list', $result['#theme']);
    $this->assertArrayHasKey('#items', $result);
    $this->assertEmpty($result['#items']);
    $this->assertArrayHasKey('#attributes', $result);
    $this->assertEquals(['geojson-directory-listing'], $result['#attributes']['class']);
  }

  /**
   * Tests non-existent directory handling.
   *
   * @covers ::buildDirectoryListing
   */
  public function testNonExistentDirectoryHandling(): void {
    $non_existent_dir = 'public://does_not_exist';

    // The GeoJson service throws an exception for non-existent directories.
    $this->expectException(\Drupal\Core\File\Exception\NotRegularDirectoryException::class);
    $this->expectExceptionMessage('public://does_not_exist is not a directory.');

    $this->geoJsonDirectoryList->buildDirectoryListing($non_existent_dir);
  }

  /**
   * Tests mixed files and directories.
   *
   * @covers ::buildDirectoryListing
   */
  public function testMixedFilesAndDirectories(): void {
    $result = $this->geoJsonDirectoryList->buildDirectoryListing($this->testDirectory);

    $has_files = FALSE;
    $has_directories = FALSE;

    foreach ($result['#items'] as $item) {
      // Check for file items (arrays with link/markup elements).
      if (is_array($item) && isset($item[0]) &&
          (isset($item[0]['#type']) || isset($item[0]['#markup']))) {
        $has_files = TRUE;
      }
      // Check for directory items (single array with #markup and children).
      if (is_array($item) && isset($item['#markup']) && isset($item['children'])) {
        $has_directories = TRUE;
      }
    }

    $this->assertTrue($has_files, 'Should have file items in mixed listing.');
    $this->assertTrue($has_directories, 'Should have directory items in mixed listing.');
  }

  /**
   * Tests file items build correctly.
   *
   * @covers ::buildFileItems
   * @covers ::buildFileLink
   * @covers ::buildFileItem
   */
  public function testFileItemsBuildCorrectly(): void {
    $result = $this->geoJsonDirectoryList->buildDirectoryListing($this->testDirectory, TRUE);

    // Find the admin1.geojson file (which shouldn't have a minified version).
    $admin1_found = FALSE;
    foreach ($result['#items'] as $item) {
      if (is_array($item) && count($item) === 1 &&
          isset($item[0]['#title']) && $item[0]['#title'] === 'admin1.geojson') {
        $admin1_found = TRUE;
        $this->assertEquals('link', $item[0]['#type']);
        $this->assertArrayHasKey('#url', $item[0]);
        break;
      }
    }
    $this->assertTrue($admin1_found, 'Should find single file without minified version.');

    // Test without links.
    $result_no_links = $this->geoJsonDirectoryList->buildDirectoryListing($this->testDirectory, FALSE);
    $admin1_markup_found = FALSE;
    foreach ($result_no_links['#items'] as $item) {
      if (is_array($item) && count($item) === 1 &&
          isset($item[0]['#markup']) && $item[0]['#markup'] === 'admin1.geojson') {
        $admin1_markup_found = TRUE;
        $this->assertArrayNotHasKey('#type', $item[0]);
        $this->assertArrayNotHasKey('#url', $item[0]);
        break;
      }
    }
    $this->assertTrue($admin1_markup_found, 'Should find file as markup when links disabled.');
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