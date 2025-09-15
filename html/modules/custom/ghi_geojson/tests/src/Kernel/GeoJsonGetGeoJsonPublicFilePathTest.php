<?php

namespace Drupal\Tests\ghi_geojson\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\ghi_geojson\GeoJson;
use Drupal\ghi_geojson\GeoJsonLocationInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for the GeoJson::getGeoJsonPublicFilePath method.
 *
 * @coversDefaultClass \Drupal\ghi_geojson\GeoJson
 * @group ghi_geojson
 */
class GeoJsonGetGeoJsonPublicFilePathTest extends KernelTestBase {

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
    $public_path = 'public://geojson';

    // Create directories for test countries and public directory.
    $countries = ['AFG', 'IRQ'];
    $versions = ['2022', '2023'];

    // Create public geojson directory.
    $this->fileSystem->prepareDirectory($public_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

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

          $test_file = $adm_directory . '/TEST_PCODE.geojson';
          file_put_contents($test_file, '{"type":"FeatureCollection","features":[]}');
        }
      }
    }
  }

  /**
   * Creates a mock location object.
   *
   * @param string $uuid
   *   The UUID for the location.
   * @param string $iso3
   *   The ISO3 code.
   * @param int $admin_level
   *   The admin level.
   * @param string $pcode
   *   The pcode.
   * @param string $version
   *   The GeoJSON version.
   *
   * @return \Drupal\ghi_geojson\GeoJsonLocationInterface
   *   A mock location object.
   */
  protected function createMockLocation($uuid, $iso3, $admin_level, $pcode = NULL, $version = 'current') {
    $location = $this->createMock(GeoJsonLocationInterface::class);
    $location->method('getUuid')->willReturn($uuid);
    $location->method('getIso3')->willReturn($iso3);
    $location->method('getAdminLevel')->willReturn($admin_level);
    $location->method('getPcode')->willReturn($pcode);
    $location->method('getGeoJsonVersion')->willReturn($version);
    return $location;
  }

  /**
   * Tests successful public file path retrieval for country level (admin 0).
   *
   * @covers ::getGeoJsonPublicFilePath
   */
  public function testGetGeoJsonPublicFilePathCountrySuccess(): void {
    $uuid = 'test-uuid-country-afg';
    $location = $this->createMockLocation($uuid, 'AFG', 0, NULL, '2023');

    // Ensure public file doesn't exist initially.
    $expected_public_path = GeoJson::GEOJSON_DIR . '/' . $uuid . '.geojson';
    $this->assertFalse(file_exists($expected_public_path), 'Public file should not exist initially.');

    // Call the method.
    $result = $this->geoJsonService->getGeoJsonPublicFilePath($location);

    // Assert the file was created and correct path returned.
    $this->assertEquals($expected_public_path, $result, 'Should return correct public file path.');
    $this->assertTrue(file_exists($expected_public_path), 'Public file should exist after method call.');

    // Verify file content was copied correctly.
    $content = file_get_contents($expected_public_path);
    $this->assertStringContainsString('FeatureCollection', $content, 'File should contain GeoJSON content.');
  }

  /**
   * Tests successful public file path retrieval for admin level 1.
   *
   * @covers ::getGeoJsonPublicFilePath
   */
  public function testGetGeoJsonPublicFilePathAdminLevelSuccess(): void {
    $uuid = 'test-uuid-admin1-afg';
    $location = $this->createMockLocation($uuid, 'AFG', 1, 'TEST_PCODE', '2023');

    // Ensure public file doesn't exist initially.
    $expected_public_path = GeoJson::GEOJSON_DIR . '/' . $uuid . '.geojson';
    $this->assertFalse(file_exists($expected_public_path), 'Public file should not exist initially.');

    // Call the method.
    $result = $this->geoJsonService->getGeoJsonPublicFilePath($location);

    // Assert the file was created and correct path returned.
    $this->assertEquals($expected_public_path, $result, 'Should return correct public file path.');
    $this->assertTrue(file_exists($expected_public_path), 'Public file should exist after method call.');

    // Verify file content was copied correctly.
    $content = file_get_contents($expected_public_path);
    $this->assertStringContainsString('FeatureCollection', $content, 'File should contain GeoJSON content.');
  }

  /**
   * Tests that existing public file is not overwritten.
   *
   * @covers ::getGeoJsonPublicFilePath
   */
  public function testGetGeoJsonPublicFilePathExistingFile(): void {
    $uuid = 'test-uuid-existing';
    $location = $this->createMockLocation($uuid, 'AFG', 0, NULL, '2023');

    $expected_public_path = GeoJson::GEOJSON_DIR . '/' . $uuid . '.geojson';
    $existing_content = '{"existing":"content"}';

    // Create existing public file.
    file_put_contents($expected_public_path, $existing_content);
    $this->assertTrue(file_exists($expected_public_path), 'Public file should exist before method call.');

    // Call the method.
    $result = $this->geoJsonService->getGeoJsonPublicFilePath($location);

    // Assert the same path is returned and content is unchanged.
    $this->assertEquals($expected_public_path, $result, 'Should return correct public file path.');
    $content = file_get_contents($expected_public_path);
    $this->assertEquals($existing_content, $content, 'Existing file content should not be overwritten.');
  }

  /**
   * Tests return value when source file doesn't exist.
   *
   * @covers ::getGeoJsonPublicFilePath
   */
  public function testGetGeoJsonPublicFilePathNoSourceFile(): void {
    $uuid = 'test-uuid-no-source';
    $location = $this->createMockLocation($uuid, 'NONEXISTENT', 0, NULL, 'current');

    $expected_public_path = GeoJson::GEOJSON_DIR . '/' . $uuid . '.geojson';

    // Ensure public file doesn't exist initially.
    $this->assertFalse(file_exists($expected_public_path), 'Public file should not exist initially.');

    // Call the method.
    $result = $this->geoJsonService->getGeoJsonPublicFilePath($location);

    // Assert NULL is returned and no file is created.
    $this->assertNull($result, 'Should return NULL when source file does not exist.');
    $this->assertFalse(file_exists($expected_public_path), 'Public file should not be created when source does not exist.');
  }

  /**
   * Tests return value when location has no ISO3 code.
   *
   * @covers ::getGeoJsonPublicFilePath
   */
  public function testGetGeoJsonPublicFilePathNoIso3(): void {
    $uuid = 'test-uuid-no-iso3';
    $location = $this->createMockLocation($uuid, NULL, 0, NULL, '2023');

    $expected_public_path = GeoJson::GEOJSON_DIR . '/' . $uuid . '.geojson';

    // Ensure public file doesn't exist initially.
    $this->assertFalse(file_exists($expected_public_path), 'Public file should not exist initially.');

    // Call the method.
    $result = $this->geoJsonService->getGeoJsonPublicFilePath($location);

    // Assert NULL is returned and no file is created.
    $this->assertNull($result, 'Should return NULL when location has no ISO3 code.');
    $this->assertFalse(file_exists($expected_public_path), 'Public file should not be created when location has no ISO3.');
  }

  /**
   * Tests behavior with invalid admin level configuration.
   *
   * @covers ::getGeoJsonPublicFilePath
   */
  public function testGetGeoJsonPublicFilePathInvalidAdminLevel(): void {
    $uuid = 'test-uuid-invalid-admin';
    // Admin level 1 without pcode should fail to find source file.
    $location = $this->createMockLocation($uuid, 'AFG', 1, NULL, '2023');

    $expected_public_path = GeoJson::GEOJSON_DIR . '/' . $uuid . '.geojson';

    // Ensure public file doesn't exist initially.
    $this->assertFalse(file_exists($expected_public_path), 'Public file should not exist initially.');

    // Call the method.
    $result = $this->geoJsonService->getGeoJsonPublicFilePath($location);

    // Assert NULL is returned and no file is created.
    $this->assertNull($result, 'Should return NULL when admin level configuration is invalid.');
    $this->assertFalse(file_exists($expected_public_path), 'Public file should not be created with invalid admin level config.');
  }

  /**
   * Tests behavior with version that finds newer version fallback.
   *
   * @covers ::getGeoJsonPublicFilePath
   */
  public function testGetGeoJsonPublicFilePathVersionFallback(): void {
    $uuid = 'test-uuid-version-fallback';
    // Request version 2000, should fallback to newer version (2022 or 2023).
    $location = $this->createMockLocation($uuid, 'AFG', 0, NULL, '2000');

    $expected_public_path = GeoJson::GEOJSON_DIR . '/' . $uuid . '.geojson';

    // Ensure public file doesn't exist initially.
    $this->assertFalse(file_exists($expected_public_path), 'Public file should not exist initially.');

    // Call the method.
    $result = $this->geoJsonService->getGeoJsonPublicFilePath($location);

    // Assert a valid path is returned (fallback to newer version works).
    $this->assertEquals($expected_public_path, $result, 'Should return public file path when version falls back to newer version.');
    $this->assertTrue(file_exists($expected_public_path), 'Public file should exist after fallback to newer version.');
  }

  /**
   * Tests behavior with version that is newer than all available versions.
   *
   * @covers ::getGeoJsonPublicFilePath
   */
  public function testGetGeoJsonPublicFilePathFutureVersion(): void {
    $uuid = 'test-uuid-future-version';
    // Request version 2050, which should be newer than all available (2022, 2023).
    $location = $this->createMockLocation($uuid, 'AFG', 0, NULL, '2050');

    $expected_public_path = GeoJson::GEOJSON_DIR . '/' . $uuid . '.geojson';

    // Ensure public file doesn't exist initially.
    $this->assertFalse(file_exists($expected_public_path), 'Public file should not exist initially.');

    // Call the method.
    $result = $this->geoJsonService->getGeoJsonPublicFilePath($location);

    // This should fall back to 'current' and return NULL since no current directory exists.
    $this->assertNull($result, 'Should return NULL when version is newer than all available and no current directory exists.');
    $this->assertFalse(file_exists($expected_public_path), 'Public file should not be created when no valid source found.');
  }

  /**
   * Tests multiple calls with same location return consistent results.
   *
   * @covers ::getGeoJsonPublicFilePath
   */
  public function testGetGeoJsonPublicFilePathConsistentResults(): void {
    $uuid = 'test-uuid-consistent';
    $location = $this->createMockLocation($uuid, 'IRQ', 0, NULL, '2022');

    // First call.
    $result1 = $this->geoJsonService->getGeoJsonPublicFilePath($location);
    $this->assertNotNull($result1, 'First call should return valid path.');

    // Second call.
    $result2 = $this->geoJsonService->getGeoJsonPublicFilePath($location);
    $this->assertEquals($result1, $result2, 'Multiple calls should return consistent results.');

    // Verify file still exists.
    $this->assertTrue(file_exists($result1), 'File should still exist after multiple calls.');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Clean up test directories if they exist.
    try {
      $paths_to_clean = [
        'public://geojson_sources',
        'public://geojson',
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