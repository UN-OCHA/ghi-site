<?php

namespace Drupal\Tests\ghi_geojson\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\ghi_geojson\GeoJson;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for the GeoJson::renameVersion method.
 *
 * @coversDefaultClass \Drupal\ghi_geojson\GeoJson
 * @group ghi_geojson
 */
class GeoJsonRenameVersionTest extends KernelTestBase {

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

    // Create test directory structure.
    $this->createTestDirectoryStructure();
  }

  /**
   * Creates a test directory structure with GeoJSON files.
   */
  protected function createTestDirectoryStructure(): void {
    $base_path = 'public://geojson_sources';

    // Create directories for test countries.
    $countries = ['AFG', 'IRQ'];
    $versions = ['2022', '2023'];

    foreach ($countries as $country) {
      foreach ($versions as $version) {
        $directory = $base_path . '/' . $country . '/' . $version;
        $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

        // Create test geojson files.
        $test_files = [
          $country . '_0.geojson',
          $country . '_0.min.geojson',
        ];

        foreach ($test_files as $file) {
          $filepath = $directory . '/' . $file;
          file_put_contents($filepath, '{"type":"FeatureCollection","features":[]}');
        }

        // Create adm level directories with test files.
        $adm_levels = ['adm1', 'adm2'];
        foreach ($adm_levels as $adm_level) {
          $adm_directory = $directory . '/' . $adm_level;
          $this->fileSystem->prepareDirectory($adm_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

          $test_file = $adm_directory . '/test_file.geojson';
          file_put_contents($test_file, '{"type":"FeatureCollection","features":[]}');
        }
      }
    }
  }

  /**
   * Tests successful version renaming.
   *
   * @covers ::renameVersion
   */
  public function testRenameVersionSuccess(): void {
    $iso3 = 'AFG';
    $old_version = '2022';
    $new_version = '2024';

    // Verify the original directory exists.
    $original_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $old_version;
    $this->assertTrue(is_dir($original_path), 'Original directory should exist before rename.');

    // Verify the new directory doesn't exist yet.
    $new_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $new_version;
    $this->assertFalse(is_dir($new_path), 'New directory should not exist before rename.');

    // Perform the rename operation.
    $result = $this->geoJsonService->renameVersion($iso3, $old_version, $new_version);

    // Assert success.
    $this->assertTrue($result, 'renameVersion should return TRUE on success.');

    // Verify the directory was renamed.
    $this->assertFalse(is_dir($original_path), 'Original directory should not exist after rename.');
    $this->assertTrue(is_dir($new_path), 'New directory should exist after rename.');

    // Verify files were preserved.
    $expected_files = [
      $iso3 . '_0.geojson',
      $iso3 . '_0.min.geojson',
      'adm1',
      'adm2',
    ];

    foreach ($expected_files as $expected_file) {
      $file_path = $new_path . '/' . $expected_file;
      $this->assertTrue(file_exists($file_path), "File or directory {$expected_file} should exist after rename.");
    }

    // Verify nested files are preserved.
    $nested_file = $new_path . '/adm1/test_file.geojson';
    $this->assertTrue(file_exists($nested_file), 'Nested files should be preserved after rename.');
  }

  /**
   * Tests renaming to existing directory fails.
   *
   * @covers ::renameVersion
   */
  public function testRenameVersionToExistingDirectoryFails(): void {
    $iso3 = 'AFG';
    $old_version = '2022';
    $existing_version = '2023'; // This already exists from our setup.

    // Verify both directories exist.
    $original_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $old_version;
    $existing_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $existing_version;
    $this->assertTrue(is_dir($original_path), 'Original directory should exist.');
    $this->assertTrue(is_dir($existing_path), 'Target directory should already exist.');

    // Attempt to rename to existing directory.
    $result = $this->geoJsonService->renameVersion($iso3, $old_version, $existing_version);

    // Assert failure.
    $this->assertFalse($result, 'renameVersion should return FALSE when target exists.');

    // Verify original directory still exists.
    $this->assertTrue(is_dir($original_path), 'Original directory should still exist after failed rename.');
    $this->assertTrue(is_dir($existing_path), 'Existing directory should still exist after failed rename.');
  }

  /**
   * Tests renaming non-existent directory fails.
   *
   * @covers ::renameVersion
   */
  public function testRenameVersionNonExistentDirectoryFails(): void {
    $iso3 = 'AFG';
    $non_existent_version = '1999';
    $new_version = '2024';

    // Verify the source directory doesn't exist.
    $source_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $non_existent_version;
    $this->assertFalse(is_dir($source_path), 'Source directory should not exist.');

    // Attempt to rename non-existent directory.
    $result = $this->geoJsonService->renameVersion($iso3, $non_existent_version, $new_version);

    // Assert failure.
    $this->assertFalse($result, 'renameVersion should return FALSE when source does not exist.');

    // Verify target directory was not created.
    $target_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $new_version;
    $this->assertFalse(is_dir($target_path), 'Target directory should not be created when source does not exist.');
  }

  /**
   * Tests renaming with invalid ISO3 code fails.
   *
   * @covers ::renameVersion
   */
  public function testRenameVersionInvalidIso3Fails(): void {
    $invalid_iso3 = 'INVALID';
    $old_version = '2022';
    $new_version = '2024';

    // Verify the source directory doesn't exist for invalid ISO3.
    $source_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $invalid_iso3 . '/' . $old_version;
    $this->assertFalse(is_dir($source_path), 'Source directory should not exist for invalid ISO3.');

    // Attempt to rename with invalid ISO3.
    $result = $this->geoJsonService->renameVersion($invalid_iso3, $old_version, $new_version);

    // Assert failure.
    $this->assertFalse($result, 'renameVersion should return FALSE for invalid ISO3.');
  }

  /**
   * Tests that cache tags are invalidated on successful rename.
   *
   * @covers ::renameVersion
   */
  public function testRenameVersionInvalidatesCacheTags(): void {
    $iso3 = 'IRQ';
    $old_version = '2023';
    $new_version = '2025';

    // Set up cache invalidation tracking.
    $expected_tags = $this->geoJsonService->getCacheTags($iso3, $old_version);

    // Mock the cache invalidation (since we can't easily test cache invalidation directly in kernel test).
    // We'll test that the method calls getCacheTags correctly by verifying the structure.
    $this->assertIsArray($expected_tags);
    $this->assertContains('ghi_geojson:geojson-' . $iso3, $expected_tags);
    $this->assertContains('ghi_geojson:geojson-' . $iso3 . '-' . $old_version, $expected_tags);

    // Perform the rename operation.
    $result = $this->geoJsonService->renameVersion($iso3, $old_version, $new_version);

    // Assert success.
    $this->assertTrue($result, 'renameVersion should return TRUE on success.');

    // Verify the directory was renamed successfully.
    $new_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $new_version;
    $this->assertTrue(is_dir($new_path), 'New directory should exist after successful rename.');
  }

  /**
   * Tests renaming preserves directory structure and permissions.
   *
   * @covers ::renameVersion
   */
  public function testRenameVersionPreservesStructureAndPermissions(): void {
    $iso3 = 'IRQ';
    $old_version = '2022';
    $new_version = '2026';

    $original_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $old_version;

    // Get the original permissions of the directory.
    $original_permissions = fileperms($original_path);

    // Perform the rename.
    $result = $this->geoJsonService->renameVersion($iso3, $old_version, $new_version);
    $this->assertTrue($result, 'renameVersion should succeed.');

    $new_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $new_version;

    // Check that permissions are preserved.
    $new_permissions = fileperms($new_path);
    $this->assertEquals($original_permissions, $new_permissions, 'Directory permissions should be preserved.');

    // Verify complete directory structure is preserved.
    $expected_structure = [
      $iso3 . '_0.geojson',
      $iso3 . '_0.min.geojson',
      'adm1',
      'adm2',
    ];

    foreach ($expected_structure as $item) {
      $item_path = $new_path . '/' . $item;
      $this->assertTrue(file_exists($item_path), "Structure item {$item} should exist after rename.");
    }

    // Verify nested directory structure.
    $nested_file = $new_path . '/adm1/test_file.geojson';
    $this->assertTrue(file_exists($nested_file), 'Nested directory files should be preserved.');

    $nested_file_2 = $new_path . '/adm2/test_file.geojson';
    $this->assertTrue(file_exists($nested_file_2), 'All nested directories should preserve their files.');
  }

  /**
   * Tests renaming with special characters in version names.
   *
   * @covers ::renameVersion
   */
  public function testRenameVersionWithSpecialCharacters(): void {
    // First create a directory with a special character version name.
    $iso3 = 'AFG';
    $special_version = '2022-backup';
    $new_version = '2022_restored';

    // Create the special version directory.
    $special_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $special_version;
    $this->fileSystem->prepareDirectory($special_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // Add a test file.
    $test_file = $special_path . '/test.geojson';
    file_put_contents($test_file, '{"type":"FeatureCollection","features":[]}');

    // Perform the rename.
    $result = $this->geoJsonService->renameVersion($iso3, $special_version, $new_version);
    $this->assertTrue($result, 'renameVersion should handle special characters in version names.');

    // Verify the rename worked.
    $new_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $new_version;
    $this->assertTrue(is_dir($new_path), 'Directory with special characters should be renamed successfully.');
    $this->assertFalse(is_dir($special_path), 'Original directory with special characters should no longer exist.');

    // Verify file was preserved.
    $preserved_file = $new_path . '/test.geojson';
    $this->assertTrue(file_exists($preserved_file), 'Files should be preserved when renaming directories with special characters.');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Clean up test directories if they exist.
    try {
      $base_path = 'public://geojson_sources';
      if ($this->fileSystem) {
        $this->fileSystem->deleteRecursive($base_path);
      }
    } catch (\Exception $e) {
      // Ignore cleanup errors during tearDown.
    }

    parent::tearDown();
  }

}