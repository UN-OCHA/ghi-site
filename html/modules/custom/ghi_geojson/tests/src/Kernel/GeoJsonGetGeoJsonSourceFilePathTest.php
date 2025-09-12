<?php

namespace Drupal\Tests\ghi_geojson\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\ghi_geojson\GeoJson;
use Drupal\ghi_geojson\GeoJsonLocationInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for the GeoJson::getGeoJsonSourceFilePath method.
 *
 * @coversDefaultClass \Drupal\ghi_geojson\GeoJson
 * @group ghi_geojson
 */
class GeoJsonGetGeoJsonSourceFilePathTest extends KernelTestBase {

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
   * Creates a test directory structure with GeoJSON source files.
   */
  protected function createTestDirectoryStructure(): void {
    $base_path = 'public://geojson_sources';

    // Create directories for test countries and versions.
    $countries = ['AFG', 'IRQ', 'SYR'];
    $versions = ['2021', '2022', '2023'];

    foreach ($countries as $country) {
      foreach ($versions as $version) {
        $directory = $base_path . '/' . $country . '/' . $version;
        $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

        // Create country-level test geojson files (admin level 0).
        $country_files = [
          $country . '_0.geojson',
          $country . '_0.min.geojson',
        ];

        foreach ($country_files as $file) {
          $filepath = $directory . '/' . $file;
          file_put_contents($filepath, '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{"ISO3":"' . $country . '"},"geometry":{"type":"Polygon","coordinates":[]}}]}');
        }

        // Create adm level directories with test files (admin level 1+).
        $adm_levels = ['adm1', 'adm2'];
        foreach ($adm_levels as $adm_level) {
          $adm_directory = $directory . '/' . $adm_level;
          $this->fileSystem->prepareDirectory($adm_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

          // Create test files with different pcodes.
          $pcodes = ['TEST_PCODE_001', 'TEST_PCODE_002'];
          foreach ($pcodes as $pcode) {
            $files = [
              $pcode . '.geojson',
              $pcode . '.min.geojson',
            ];
            foreach ($files as $file) {
              $filepath = $adm_directory . '/' . $file;
              file_put_contents($filepath, '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{"PCODE":"' . $pcode . '"},"geometry":{"type":"Polygon","coordinates":[]}}]}');
            }
          }
        }
      }
    }

    // Create a 'current' directory for one country.
    $current_directory = $base_path . '/AFG/current';
    $this->fileSystem->prepareDirectory($current_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    file_put_contents($current_directory . '/AFG_0.min.geojson', '{"type":"FeatureCollection","features":[]}');
  }

  /**
   * Creates a mock location object.
   *
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
  protected function createMockLocation($iso3, $admin_level, $pcode = NULL, $version = 'current') {
    $location = $this->createMock(GeoJsonLocationInterface::class);
    $location->method('getIso3')->willReturn($iso3);
    $location->method('getAdminLevel')->willReturn($admin_level);
    $location->method('getPcode')->willReturn($pcode);
    $location->method('getGeoJsonVersion')->willReturn($version);
    return $location;
  }

  /**
   * Tests successful source file path retrieval for country level (admin 0).
   *
   * @covers ::getGeoJsonSourceFilePath
   */
  public function testGetGeoJsonSourceFilePathCountryMinified(): void {
    $location = $this->createMockLocation('AFG', 0, NULL, '2023');

    $result = $this->geoJsonService->getGeoJsonSourceFilePath($location);

    $expected_path = GeoJson::GEOJSON_SOURCE_DIR . '/AFG/2023/AFG_0.min.geojson';
    $this->assertEquals($expected_path, $result, 'Should return correct minified source file path for country.');
    $this->assertTrue(file_exists($result), 'Returned file path should exist.');
  }

  /**
   * Tests successful source file path retrieval for country level non-minified.
   *
   * @covers ::getGeoJsonSourceFilePath
   */
  public function testGetGeoJsonSourceFilePathCountryNonMinified(): void {
    $location = $this->createMockLocation('IRQ', 0, NULL, '2022');

    $result = $this->geoJsonService->getGeoJsonSourceFilePath($location, NULL, FALSE);

    $expected_path = GeoJson::GEOJSON_SOURCE_DIR . '/IRQ/2023/IRQ_0.geojson';
    $this->assertEquals($expected_path, $result, 'Should return correct non-minified source file path for country.');
    $this->assertTrue(file_exists($result), 'Returned file path should exist.');
  }

  /**
   * Tests successful source file path retrieval for admin level 1.
   *
   * @covers ::getGeoJsonSourceFilePath
   */
  public function testGetGeoJsonSourceFilePathAdminLevel1(): void {
    $location = $this->createMockLocation('SYR', 1, 'TEST_PCODE_001', '2021');

    $result = $this->geoJsonService->getGeoJsonSourceFilePath($location);

    $expected_path = GeoJson::GEOJSON_SOURCE_DIR . '/SYR/2023/adm1/TEST_PCODE_001.min.geojson';
    $this->assertEquals($expected_path, $result, 'Should return correct source file path for admin level 1.');
    $this->assertTrue(file_exists($result), 'Returned file path should exist.');
  }

  /**
   * Tests successful source file path retrieval for admin level 2.
   *
   * @covers ::getGeoJsonSourceFilePath
   */
  public function testGetGeoJsonSourceFilePathAdminLevel2(): void {
    $location = $this->createMockLocation('AFG', 2, 'TEST_PCODE_002', '2023');

    $result = $this->geoJsonService->getGeoJsonSourceFilePath($location, NULL, FALSE);

    $expected_path = GeoJson::GEOJSON_SOURCE_DIR . '/AFG/2023/adm2/TEST_PCODE_002.geojson';
    $this->assertEquals($expected_path, $result, 'Should return correct non-minified source file path for admin level 2.');
    $this->assertTrue(file_exists($result), 'Returned file path should exist.');
  }

  /**
   * Tests version fallback behavior when requesting older version.
   *
   * @covers ::getGeoJsonSourceFilePath
   */
  public function testGetGeoJsonSourceFilePathVersionFallback(): void {
    // Request version 2020, should fallback to 2023 (newest available >= 2020).
    $location = $this->createMockLocation('IRQ', 0, NULL, '2020');

    $result = $this->geoJsonService->getGeoJsonSourceFilePath($location);

    $expected_path = GeoJson::GEOJSON_SOURCE_DIR . '/IRQ/2023/IRQ_0.min.geojson';
    $this->assertEquals($expected_path, $result, 'Should fallback to newest available version when requested version is older.');
    $this->assertTrue(file_exists($result), 'Fallback file path should exist.');
  }

  /**
   * Tests version fallback behavior when requesting version between available ones.
   *
   * @covers ::getGeoJsonSourceFilePath
   */
  public function testGetGeoJsonSourceFilePathVersionFallbackMiddle(): void {
    // Request version 2022 for SYR, should get 2023 (newest >= 2022).
    $location = $this->createMockLocation('SYR', 0, NULL, '2022');

    $result = $this->geoJsonService->getGeoJsonSourceFilePath($location);

    $expected_path = GeoJson::GEOJSON_SOURCE_DIR . '/SYR/2023/SYR_0.min.geojson';
    $this->assertEquals($expected_path, $result, 'Should return newest available version when version falls between available ones.');
    $this->assertTrue(file_exists($result), 'Fallback version file path should exist.');
  }

  /**
   * Tests version fallback behavior when requesting future version.
   *
   * @covers ::getGeoJsonSourceFilePath
   */
  public function testGetGeoJsonSourceFilePathFutureVersionFallback(): void {
    // Request version 2025, should fallback to 'current'.
    $location = $this->createMockLocation('AFG', 0, NULL, '2025');

    $result = $this->geoJsonService->getGeoJsonSourceFilePath($location);

    $expected_path = GeoJson::GEOJSON_SOURCE_DIR . '/AFG/current/AFG_0.min.geojson';
    $this->assertEquals($expected_path, $result, 'Should fallback to current version when requested version is newer than all available.');
    $this->assertTrue(file_exists($result), 'Current version file path should exist.');
  }

  /**
   * Tests fallback to non-minified when minified file doesn't exist.
   *
   * @covers ::getGeoJsonSourceFilePath
   */
  public function testGetGeoJsonSourceFilePathFallbackToNonMinified(): void {
    // Remove minified file to test fallback.
    $minified_path = GeoJson::GEOJSON_SOURCE_DIR . '/IRQ/2023/IRQ_0.min.geojson';
    if (file_exists($minified_path)) {
      unlink($minified_path);
    }

    $location = $this->createMockLocation('IRQ', 0, NULL, '2023');

    $result = $this->geoJsonService->getGeoJsonSourceFilePath($location);

    $expected_path = GeoJson::GEOJSON_SOURCE_DIR . '/IRQ/2023/IRQ_0.geojson';
    $this->assertEquals($expected_path, $result, 'Should fallback to non-minified when minified version does not exist.');
    $this->assertTrue(file_exists($result), 'Non-minified fallback file should exist.');
  }

  /**
   * Tests return NULL when location has no ISO3 code.
   *
   * @covers ::getGeoJsonSourceFilePath
   */
  public function testGetGeoJsonSourceFilePathNoIso3(): void {
    $location = $this->createMockLocation(NULL, 0, NULL, '2023');

    $result = $this->geoJsonService->getGeoJsonSourceFilePath($location);

    $this->assertNull($result, 'Should return NULL when location has no ISO3 code.');
  }

  /**
   * Tests return NULL when location has empty ISO3 code.
   *
   * @covers ::getGeoJsonSourceFilePath
   */
  public function testGetGeoJsonSourceFilePathEmptyIso3(): void {
    $location = $this->createMockLocation('', 0, NULL, '2023');

    $result = $this->geoJsonService->getGeoJsonSourceFilePath($location);

    $this->assertNull($result, 'Should return NULL when location has empty ISO3 code.');
  }

  /**
   * Tests exception when country directory doesn't exist.
   *
   * @covers ::getGeoJsonSourceFilePath
   */
  public function testGetGeoJsonSourceFilePathNoCountryDirectory(): void {
    $location = $this->createMockLocation('XXX', 0, NULL, '2023');

    $this->expectException(\Drupal\Core\File\Exception\NotRegularDirectoryException::class);

    $this->geoJsonService->getGeoJsonSourceFilePath($location);
  }

  /**
   * Tests return NULL when admin level 1+ has no pcode.
   *
   * @covers ::getGeoJsonSourceFilePath
   */
  public function testGetGeoJsonSourceFilePathAdminLevelNoPcode(): void {
    $location = $this->createMockLocation('AFG', 1, NULL, '2023');

    $result = $this->geoJsonService->getGeoJsonSourceFilePath($location);

    $this->assertNull($result, 'Should return NULL when admin level 1+ has no pcode.');
  }

  /**
   * Tests return NULL when admin level 1+ has empty pcode.
   *
   * @covers ::getGeoJsonSourceFilePath
   */
  public function testGetGeoJsonSourceFilePathAdminLevelEmptyPcode(): void {
    $location = $this->createMockLocation('AFG', 2, '', '2023');

    $result = $this->geoJsonService->getGeoJsonSourceFilePath($location);

    $this->assertNull($result, 'Should return NULL when admin level 2 has empty pcode.');
  }

  /**
   * Tests return NULL when requested file doesn't exist and no fallback available.
   *
   * @covers ::getGeoJsonSourceFilePath
   */
  public function testGetGeoJsonSourceFilePathNoFileExists(): void {
    $location = $this->createMockLocation('AFG', 1, 'NONEXISTENT_PCODE', '2023');

    $result = $this->geoJsonService->getGeoJsonSourceFilePath($location);

    $this->assertNull($result, 'Should return NULL when requested file does not exist.');
  }

  /**
   * Tests using explicit version parameter over location version.
   *
   * @covers ::getGeoJsonSourceFilePath
   */
  public function testGetGeoJsonSourceFilePathExplicitVersion(): void {
    // Location has version 2023, but we explicitly request 2021 (which falls back to 2023).
    $location = $this->createMockLocation('SYR', 0, NULL, '2023');

    $result = $this->geoJsonService->getGeoJsonSourceFilePath($location, '2021');

    $expected_path = GeoJson::GEOJSON_SOURCE_DIR . '/SYR/2023/SYR_0.min.geojson';
    $this->assertEquals($expected_path, $result, 'Should use explicit version parameter and fallback to newest available.');
    $this->assertTrue(file_exists($result), 'Explicit version file should exist.');
  }

  /**
   * Tests exact version match when requesting available version.
   *
   * @covers ::getGeoJsonSourceFilePath
   */
  public function testGetGeoJsonSourceFilePathExactVersionMatch(): void {
    // Request exactly version 2023 which should be found.
    $location = $this->createMockLocation('AFG', 0, NULL, '2023');

    $result = $this->geoJsonService->getGeoJsonSourceFilePath($location, '2023');

    $expected_path = GeoJson::GEOJSON_SOURCE_DIR . '/AFG/2023/AFG_0.min.geojson';
    $this->assertEquals($expected_path, $result, 'Should return exact version when it exists.');
    $this->assertTrue(file_exists($result), 'Exact version file should exist.');
  }

  /**
   * Tests 'current' version handling.
   *
   * @covers ::getGeoJsonSourceFilePath
   */
  public function testGetGeoJsonSourceFilePathCurrentVersion(): void {
    $location = $this->createMockLocation('AFG', 0, NULL, 'current');

    $result = $this->geoJsonService->getGeoJsonSourceFilePath($location);

    $expected_path = GeoJson::GEOJSON_SOURCE_DIR . '/AFG/current/AFG_0.min.geojson';
    $this->assertEquals($expected_path, $result, 'Should handle current version correctly.');
    $this->assertTrue(file_exists($result), 'Current version file should exist.');
  }

  /**
   * Tests return NULL when 'current' directory doesn't exist.
   *
   * @covers ::getGeoJsonSourceFilePath
   */
  public function testGetGeoJsonSourceFilePathCurrentVersionNotExists(): void {
    // IRQ doesn't have a 'current' directory in our test setup.
    $location = $this->createMockLocation('IRQ', 0, NULL, 'current');

    $result = $this->geoJsonService->getGeoJsonSourceFilePath($location);

    $this->assertNull($result, 'Should return NULL when current version directory does not exist.');
  }

  /**
   * Tests multiple calls return consistent results.
   *
   * @covers ::getGeoJsonSourceFilePath
   */
  public function testGetGeoJsonSourceFilePathConsistentResults(): void {
    $location = $this->createMockLocation('AFG', 1, 'TEST_PCODE_001', '2022');

    $result1 = $this->geoJsonService->getGeoJsonSourceFilePath($location);
    $result2 = $this->geoJsonService->getGeoJsonSourceFilePath($location);

    $this->assertEquals($result1, $result2, 'Multiple calls should return consistent results.');
    $this->assertNotNull($result1, 'Result should not be NULL.');
    $this->assertTrue(file_exists($result1), 'File should exist.');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Clean up test directories if they exist.
    try {
      $paths_to_clean = [
        'public://geojson_sources',
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