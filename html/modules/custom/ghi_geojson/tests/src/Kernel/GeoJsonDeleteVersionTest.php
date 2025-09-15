<?php

namespace Drupal\Tests\ghi_geojson\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\ghi_geojson\GeoJson;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for the GeoJson::deleteVersion method.
 *
 * @coversDefaultClass \Drupal\ghi_geojson\GeoJson
 * @group ghi_geojson
 */
class GeoJsonDeleteVersionTest extends KernelTestBase {

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
    $countries = ['AFG', 'IRQ', 'SYR'];
    $versions = ['2020', '2021', '2022', 'current'];

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
   * Tests successful version deletion.
   *
   * @covers ::deleteVersion
   */
  public function testDeleteVersionSuccess(): void {
    $iso3 = 'AFG';
    $version = '2022';

    // Verify the directory exists before deletion.
    $version_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $version;
    $this->assertTrue(is_dir($version_path), 'Version directory should exist before deletion.');

    // Verify files exist in the directory.
    $expected_files = [
      $version_path . '/' . $iso3 . '_0.geojson',
      $version_path . '/' . $iso3 . '_0.min.geojson',
      $version_path . '/adm1/test_file.geojson',
      $version_path . '/adm2/test_file.geojson',
    ];

    foreach ($expected_files as $file) {
      $this->assertTrue(file_exists($file), "File {$file} should exist before deletion.");
    }

    // Perform the deletion.
    $result = $this->geoJsonService->deleteVersion($iso3, $version);

    // Assert success.
    $this->assertTrue($result, 'deleteVersion should return TRUE on success.');

    // Verify the directory and all files were deleted.
    $this->assertFalse(is_dir($version_path), 'Version directory should not exist after deletion.');

    foreach ($expected_files as $file) {
      $this->assertFalse(file_exists($file), "File {$file} should not exist after deletion.");
    }

    // Verify other versions of the same country still exist.
    $other_version_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/2021';
    $this->assertTrue(is_dir($other_version_path), 'Other versions should remain after deletion.');
  }

  /**
   * Tests deletion of non-existent version.
   *
   * @covers ::deleteVersion
   */
  public function testDeleteVersionNonExistent(): void {
    $iso3 = 'AFG';
    $non_existent_version = '1999';

    // Verify the directory doesn't exist.
    $version_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $non_existent_version;
    $this->assertFalse(is_dir($version_path), 'Non-existent version directory should not exist.');

    // Attempt to delete non-existent version.
    $result = $this->geoJsonService->deleteVersion($iso3, $non_existent_version);

    // FileSystem::deleteRecursive() returns TRUE even if the directory doesn't exist.
    $this->assertTrue($result, 'deleteVersion should return TRUE even for non-existent directories.');
  }

  /**
   * Tests deletion with invalid ISO3 code.
   *
   * @covers ::deleteVersion
   */
  public function testDeleteVersionInvalidIso3(): void {
    $invalid_iso3 = 'INVALID';
    $version = '2022';

    // Verify the directory doesn't exist for invalid ISO3.
    $version_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $invalid_iso3 . '/' . $version;
    $this->assertFalse(is_dir($version_path), 'Directory should not exist for invalid ISO3.');

    // Attempt to delete with invalid ISO3.
    $result = $this->geoJsonService->deleteVersion($invalid_iso3, $version);

    // FileSystem::deleteRecursive() returns TRUE even if the directory doesn't exist.
    $this->assertTrue($result, 'deleteVersion should return TRUE even for invalid ISO3.');
  }

  /**
   * Tests that trying to delete 'current' version throws exception.
   *
   * @covers ::deleteVersion
   */
  public function testDeleteCurrentVersionThrowsException(): void {
    $iso3 = 'AFG';
    $current_version = 'current';

    // Verify the current version directory exists.
    $current_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $current_version;
    $this->assertTrue(is_dir($current_path), 'Current version directory should exist.');

    // Expect exception when trying to delete 'current' version.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Current GeoJSON versions cannot be deleted (country: AFG)');

    // Attempt to delete current version.
    $this->geoJsonService->deleteVersion($iso3, $current_version);
  }

  /**
   * Tests deletion preserves other countries' data.
   *
   * @covers ::deleteVersion
   */
  public function testDeleteVersionPreservesOtherCountries(): void {
    $target_iso3 = 'AFG';
    $other_iso3 = 'IRQ';
    $version = '2021';

    // Verify both countries have the version directory.
    $target_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $target_iso3 . '/' . $version;
    $other_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $other_iso3 . '/' . $version;
    $this->assertTrue(is_dir($target_path), 'Target country version should exist.');
    $this->assertTrue(is_dir($other_path), 'Other country version should exist.');

    // Delete version for target country.
    $result = $this->geoJsonService->deleteVersion($target_iso3, $version);
    $this->assertTrue($result, 'deleteVersion should succeed.');

    // Verify only the target country's version was deleted.
    $this->assertFalse(is_dir($target_path), 'Target country version should be deleted.');
    $this->assertTrue(is_dir($other_path), 'Other country version should still exist.');

    // Verify files in other country are still intact.
    $other_file = $other_path . '/' . $other_iso3 . '_0.geojson';
    $this->assertTrue(file_exists($other_file), 'Files in other countries should remain intact.');
  }

  /**
   * Tests deletion of version with complex directory structure.
   *
   * @covers ::deleteVersion
   */
  public function testDeleteVersionComplexStructure(): void {
    $iso3 = 'SYR';
    $version = '2020';

    // Create additional complex structure.
    $version_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $version;

    // Create nested subdirectories.
    $deep_dir = $version_path . '/adm3/subregion';
    $this->fileSystem->prepareDirectory($deep_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // Create files in nested directory.
    $nested_file = $deep_dir . '/nested_file.geojson';
    file_put_contents($nested_file, '{"type":"FeatureCollection","features":[]}');

    // Create multiple files in the same directory.
    $additional_files = [
      $version_path . '/additional1.json',
      $version_path . '/additional2.txt',
      $version_path . '/adm1/extra_file.geojson',
    ];

    foreach ($additional_files as $file) {
      file_put_contents($file, 'test content');
    }

    // Verify all files exist before deletion.
    $this->assertTrue(file_exists($nested_file), 'Nested file should exist before deletion.');
    foreach ($additional_files as $file) {
      $this->assertTrue(file_exists($file), "File {$file} should exist before deletion.");
    }

    // Perform deletion.
    $result = $this->geoJsonService->deleteVersion($iso3, $version);
    $this->assertTrue($result, 'deleteVersion should succeed for complex structure.');

    // Verify entire directory structure was deleted.
    $this->assertFalse(is_dir($version_path), 'Version directory should be completely deleted.');
    $this->assertFalse(file_exists($nested_file), 'Nested files should be deleted.');

    foreach ($additional_files as $file) {
      $this->assertFalse(file_exists($file), "File {$file} should be deleted.");
    }
  }

  /**
   * Tests deletion with empty version directory.
   *
   * @covers ::deleteVersion
   */
  public function testDeleteVersionEmptyDirectory(): void {
    $iso3 = 'AFG';
    $empty_version = '2019';

    // Create empty version directory.
    $empty_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $empty_version;
    $this->fileSystem->prepareDirectory($empty_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // Verify directory exists but is empty.
    $this->assertTrue(is_dir($empty_path), 'Empty directory should exist.');
    $files = scandir($empty_path);
    // scandir returns . and .. entries.
    $this->assertEquals(2, count($files), 'Directory should be empty except for . and .. entries.');

    // Delete the empty directory.
    $result = $this->geoJsonService->deleteVersion($iso3, $empty_version);
    $this->assertTrue($result, 'deleteVersion should succeed for empty directory.');

    // Verify directory was deleted.
    $this->assertFalse(is_dir($empty_path), 'Empty directory should be deleted.');
  }

  /**
   * Tests deletion with special characters in version name.
   *
   * @covers ::deleteVersion
   */
  public function testDeleteVersionSpecialCharacters(): void {
    $iso3 = 'IRQ';
    $special_version = '2022-backup_v1.0';

    // Create directory with special characters in version name.
    $special_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $iso3 . '/' . $special_version;
    $this->fileSystem->prepareDirectory($special_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // Add test file.
    $test_file = $special_path . '/test.geojson';
    file_put_contents($test_file, '{"type":"FeatureCollection","features":[]}');

    // Verify directory and file exist.
    $this->assertTrue(is_dir($special_path), 'Directory with special characters should exist.');
    $this->assertTrue(file_exists($test_file), 'Test file should exist.');

    // Delete version with special characters.
    $result = $this->geoJsonService->deleteVersion($iso3, $special_version);
    $this->assertTrue($result, 'deleteVersion should handle special characters in version names.');

    // Verify deletion.
    $this->assertFalse(is_dir($special_path), 'Directory with special characters should be deleted.');
    $this->assertFalse(file_exists($test_file), 'Files should be deleted along with directory.');
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