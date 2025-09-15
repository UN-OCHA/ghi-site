<?php

namespace Drupal\Tests\ghi_geojson\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\ghi_geojson\Controller\GeoJsonSourcesController;
use Drupal\ghi_geojson\GeoJson;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kernel tests for the GeoJsonSourcesController.
 *
 * @coversDefaultClass \Drupal\ghi_geojson\Controller\GeoJsonSourcesController
 * @group ghi_geojson
 */
class GeoJsonSourcesControllerTest extends KernelTestBase {

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
    'layout_builder_modal',
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
   * GeoJSON directory list service.
   *
   * @var \Drupal\ghi_geojson\GeoJsonDirectoryList
   */
  protected $geoJsonDirectoryList;

  /**
   * Controller under test.
   *
   * @var \Drupal\ghi_geojson\Controller\GeoJsonSourcesController
   */
  protected $controller;

  /**
   * Test country ISO3 code.
   *
   * @var string
   */
  protected $testIso3 = 'TST';

  /**
   * Test version.
   *
   * @var string
   */
  protected $testVersion = '2023';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('system', ['sequences']);
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installConfig(['layout_builder_modal']);

    $this->fileSystem = $this->container->get('file_system');
    $this->geoJsonService = $this->container->get('geojson');
    $this->geoJsonDirectoryList = $this->container->get('geojson.directory_list');

    // Create the controller using dependency injection.
    $this->controller = GeoJsonSourcesController::create($this->container);

    // Create test directory structure.
    $this->createTestDirectoryStructure();
  }

  /**
   * Creates a test directory structure with GeoJSON files.
   */
  protected function createTestDirectoryStructure(): void {
    $base_path = GeoJson::GEOJSON_SOURCE_DIR;

    // Create main sources directory.
    $this->fileSystem->prepareDirectory($base_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // Create test country directory.
    $country_path = $base_path . '/' . $this->testIso3;
    $this->fileSystem->prepareDirectory($country_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // Create version directories.
    $versions = [$this->testVersion, 'current', '2022'];
    foreach ($versions as $version) {
      $version_path = $country_path . '/' . $version;
      $this->fileSystem->prepareDirectory($version_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

      // Create admin level subdirectories.
      $admin_levels = ['adm1', 'adm2', 'adm3'];
      foreach ($admin_levels as $admin_level) {
        $admin_path = $version_path . '/' . $admin_level;
        $this->fileSystem->prepareDirectory($admin_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

        // Create some test files.
        $test_files = [
          'region1.geojson' => '{"type": "FeatureCollection", "features": []}',
          'region2.geojson' => '{"type": "FeatureCollection", "features": []}',
          'region1.min.geojson' => '{"type":"FeatureCollection","features":[]}',
        ];

        foreach ($test_files as $filename => $content) {
          $filepath = $this->fileSystem->realpath($admin_path) . '/' . $filename;
          file_put_contents($filepath, $content);
        }
      }

      // Create some root level files.
      $root_files = [
        'country.geojson' => '{"type": "FeatureCollection", "features": []}',
        'country.min.geojson' => '{"type":"FeatureCollection","features":[]}',
      ];

      foreach ($root_files as $filename => $content) {
        $filepath = $this->fileSystem->realpath($version_path) . '/' . $filename;
        file_put_contents($filepath, $content);
      }
    }

    // Create another country for testing rows.
    $another_country_path = $base_path . '/ABC';
    $this->fileSystem->prepareDirectory($another_country_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    $abc_version_path = $another_country_path . '/2023';
    $this->fileSystem->prepareDirectory($abc_version_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // Create admin directories for ABC.
    $admin_levels = ['adm1', 'adm2'];
    foreach ($admin_levels as $admin_level) {
      $admin_path = $abc_version_path . '/' . $admin_level;
      $this->fileSystem->prepareDirectory($admin_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

      // Add a single test file.
      file_put_contents($this->fileSystem->realpath($admin_path) . '/test.geojson', '{"type": "FeatureCollection", "features": []}');
    }
  }

  /**
   * Tests sources page basic functionality.
   *
   * @covers ::sourcesPage
   * @covers ::buildRows
   */
  public function testSourcesPageBasicFunctionality(): void {
    $result = $this->controller->sourcesPage();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('#type', $result);
    $this->assertEquals('table', $result['#type']);

    $this->assertArrayHasKey('#header', $result);
    $expected_headers = [
      'Country code',
      'Version',
      'adm1',
      'adm2',
      'adm3',
      'Operations',
    ];
    $this->assertCount(6, $result['#header']);

    $this->assertArrayHasKey('#rows', $result);
    $this->assertNotEmpty($result['#rows']);

    // Should have rows for our test countries.
    $this->assertGreaterThan(0, count($result['#rows']));

    // Find a row to verify structure.
    $found_test_row = FALSE;
    foreach ($result['#rows'] as $row) {
      if ($row[0] === $this->testIso3) {
        $found_test_row = TRUE;
        $this->assertCount(6, $row);
        // Verify version column has link structure.
        $this->assertArrayHasKey('data', $row[1]);
        $this->assertArrayHasKey('#type', $row[1]['data']);
        $this->assertEquals('link', $row[1]['data']['#type']);
        // Verify operations column has dropbutton structure.
        $this->assertArrayHasKey('data', $row[5]);
        $this->assertArrayHasKey('#type', $row[5]['data']);
        $this->assertEquals('dropbutton', $row[5]['data']['#type']);
        break;
      }
    }
    $this->assertTrue($found_test_row, 'Should find test country row in sources page.');
  }

  /**
   * Tests directory title generation.
   *
   * @covers ::directoryTitle
   */
  public function testDirectoryTitleGeneration(): void {
    $title = $this->controller->directoryTitle($this->testIso3, $this->testVersion);
    $expected = 'File list for ' . $this->testIso3 . ' (' . $this->testVersion . ')';
    $this->assertEquals($expected, $title->__toString());

    // Test with current version.
    $title_current = $this->controller->directoryTitle($this->testIso3, 'current');
    $expected_current = 'File list for ' . $this->testIso3 . ' (current)';
    $this->assertEquals($expected_current, $title_current->__toString());

    // Test with different parameters.
    $title_other = $this->controller->directoryTitle('ABC', '2024');
    $expected_other = 'File list for ABC (2024)';
    $this->assertEquals($expected_other, $title_other->__toString());
  }

  /**
   * Tests directory listing with valid path.
   *
   * @covers ::directoryListing
   */
  public function testDirectoryListingValidPath(): void {
    $result = $this->controller->directoryListing($this->testIso3, $this->testVersion);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('#theme', $result);
    $this->assertEquals('item_list', $result['#theme']);
    $this->assertArrayHasKey('#items', $result);
    $this->assertArrayHasKey('#attributes', $result);
    $this->assertEquals(['geojson-directory-listing'], $result['#attributes']['class']);
    $this->assertArrayHasKey('#attached', $result);
    $this->assertEquals(['ghi_geojson/geojson_admin'], $result['#attached']['library']);

    // Should have items (files and directories).
    $this->assertNotEmpty($result['#items']);
  }

  /**
   * Tests directory listing with invalid path.
   *
   * @covers ::directoryListing
   */
  public function testDirectoryListingInvalidPath(): void {
    $result = $this->controller->directoryListing('INVALID', 'nonexistent');

    $this->assertInstanceOf(Response::class, $result);
    $this->assertEquals(400, $result->getStatusCode());
    $this->assertEquals('There was an error', $result->getContent());
  }

  /**
   * Tests directory download method exists and has proper signature.
   *
   * @covers ::directoryDownload
   */
  public function testDirectoryDownloadMethodExists(): void {
    // Test method exists and is callable.
    $this->assertTrue(method_exists($this->controller, 'directoryDownload'));

    // Test method signature by calling with obviously invalid parameters
    // which should trigger the error path without complex zip operations.
    $result = $this->controller->directoryDownload('DEFINITELY_NOT_EXISTS', 'invalid_version');
    $this->assertInstanceOf(Response::class, $result);
    $this->assertEquals(400, $result->getStatusCode());
    $this->assertEquals('There was an error', $result->getContent());
  }

  /**
   * Tests directory download with invalid parameters.
   *
   * @covers ::directoryDownload
   */
  public function testDirectoryDownloadError(): void {
    $result = $this->controller->directoryDownload('INVALID', 'nonexistent');

    $this->assertInstanceOf(Response::class, $result);
    $this->assertEquals(400, $result->getStatusCode());
    $this->assertEquals('There was an error', $result->getContent());
  }

  /**
   * Tests delete version protection for current version.
   *
   * @covers ::deleteVersion
   */
  public function testDeleteVersionProtectionCurrent(): void {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Current GeoJSON versions cannot be deleted (country: ' . $this->testIso3 . ')');

    $this->controller->deleteVersion($this->testIso3, 'current');
  }

  /**
   * Tests delete version success.
   *
   * @covers ::deleteVersion
   */
  public function testDeleteVersionSuccess(): void {
    // Verify directory exists before deletion.
    $directory_path = GeoJson::GEOJSON_SOURCE_DIR . '/' . $this->testIso3 . '/2022';
    $this->assertTrue(is_dir($directory_path), 'Directory should exist before deletion.');

    $result = $this->controller->deleteVersion($this->testIso3, '2022');

    $this->assertTrue($result);

    // Verify directory is deleted.
    $this->assertFalse(is_dir($directory_path), 'Directory should be deleted after deleteVersion.');
  }

  /**
   * Tests build rows with data.
   *
   * @covers ::buildRows
   */
  public function testBuildRowsWithData(): void {
    $rows = $this->controller->buildRows();

    $this->assertIsArray($rows);
    $this->assertNotEmpty($rows);

    // Should have rows for our test countries.
    $found_countries = [];
    foreach ($rows as $row) {
      $found_countries[] = $row[0];
    }

    $this->assertContains($this->testIso3, $found_countries);
    $this->assertContains('ABC', $found_countries);

    // Find a specific row to test structure.
    $test_row = null;
    foreach ($rows as $row) {
      if ($row[0] === $this->testIso3 && $row[1]['data']['#title'] === $this->testVersion) {
        $test_row = $row;
        break;
      }
    }

    $this->assertNotNull($test_row, 'Should find test row in built rows.');
    $this->assertCount(6, $test_row);

    // Test country code.
    $this->assertEquals($this->testIso3, $test_row[0]);

    // Test version link structure.
    $this->assertIsArray($test_row[1]);
    $this->assertArrayHasKey('data', $test_row[1]);
    $this->assertEquals('link', $test_row[1]['data']['#type']);
    $this->assertEquals($this->testVersion, $test_row[1]['data']['#title']);

    // Test file counts (should be greater than 0 because we created files).
    $this->assertGreaterThan(0, $test_row[2]); // adm1
    $this->assertGreaterThan(0, $test_row[3]); // adm2
    $this->assertGreaterThan(0, $test_row[4]); // adm3

    // Test operations dropbutton.
    $this->assertIsArray($test_row[5]);
    $this->assertArrayHasKey('data', $test_row[5]);
    $this->assertEquals('dropbutton', $test_row[5]['data']['#type']);
    $this->assertArrayHasKey('#links', $test_row[5]['data']);

    // Should have inspect and download links, and potentially delete if not current version.
    $links = $test_row[5]['data']['#links'];
    $this->assertArrayHasKey('inspect', $links);
    $this->assertArrayHasKey('download', $links);

    // Note: Delete link may not appear if user doesn't have access permissions,
    // so we just verify the basic structure exists.
    $this->assertIsArray($links);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Clean up test directories if they exist.
    try {
      $paths_to_clean = [
        GeoJson::GEOJSON_SOURCE_DIR,
        GeoJson::ARCHIVE_TEMP_DIR,
      ];

      foreach ($paths_to_clean as $path) {
        if ($this->fileSystem && is_dir($path)) {
          $this->fileSystem->deleteRecursive($path);
        }
      }
    }
    catch (\Exception $e) {
      // Ignore cleanup errors during tearDown.
    }

    parent::tearDown();
  }

}