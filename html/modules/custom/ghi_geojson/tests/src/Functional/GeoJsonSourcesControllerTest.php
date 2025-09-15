<?php

namespace Drupal\Tests\ghi_geojson\Functional;

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\Tests\BrowserTestBase;

/**
 * Functional tests for the GeoJsonSourcesController.
 *
 * @coversDefaultClass \Drupal\ghi_geojson\Controller\GeoJsonSourcesController
 * @group ghi_geojson
 */
class GeoJsonSourcesControllerTest extends BrowserTestBase {

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
    'node',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * Test user with view permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $viewUser;

  /**
   * Test user with admin permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

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

    $this->fileSystem = $this->container->get('file_system');
    $this->geoJsonService = $this->container->get('geojson');

    // Create test users.
    $this->viewUser = $this->drupalCreateUser([
      'view ghi geojson files',
      'access administration pages',
    ]);

    $this->adminUser = $this->drupalCreateUser([
      'view ghi geojson files',
      'administer ghi geojson files',
      'access administration pages',
      'administer site configuration',
    ]);

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
    $versions = ['current', '2023', '2022'];

    foreach ($countries as $country) {
      foreach ($versions as $version) {
        $directory = $base_path . '/' . $country . '/' . $version;
        $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

        // Create test geojson files.
        $files_to_create = [
          $country . '_0.geojson',
          $country . '_0.min.geojson',
        ];

        foreach ($files_to_create as $file) {
          $filepath = $directory . '/' . $file;
          file_put_contents($filepath, '{"type":"FeatureCollection","features":[]}');
        }

        // Create adm level directories with test files.
        $adm_levels = ['adm1', 'adm2', 'adm3'];
        foreach ($adm_levels as $adm_level) {
          $adm_directory = $directory . '/' . $adm_level;
          $this->fileSystem->prepareDirectory($adm_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

          // Create a few test files in each admin level.
          for ($i = 1; $i <= 3; $i++) {
            $test_file = $adm_directory . '/test_' . $i . '.geojson';
            file_put_contents($test_file, '{"type":"FeatureCollection","features":[]}');
          }
        }
      }
    }
  }

  /**
   * Tests that the sources page renders a table correctly.
   */
  public function testSourcesPageRendersTable(): void {
    $this->drupalLogin($this->viewUser);
    $this->drupalGet('/admin/config/ghi/geojson');

    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('GeoJSON source files');

    // Check table structure.
    $assert_session->elementExists('css', 'table');
    $assert_session->pageTextContains('Country code');
    $assert_session->pageTextContains('Version');
    $assert_session->pageTextContains('adm1');
    $assert_session->pageTextContains('adm2');
    $assert_session->pageTextContains('adm3');
    $assert_session->pageTextContains('Operations');

    // Check that test countries appear.
    $assert_session->pageTextContains('AFG');
    $assert_session->pageTextContains('IRQ');
    $assert_session->pageTextContains('SYR');

    // Check that versions appear.
    $assert_session->pageTextContains('current');
    $assert_session->pageTextContains('2023');
    $assert_session->pageTextContains('2022');
  }

  /**
   * Tests that directory listing displays files correctly.
   */
  public function testDirectoryListingDisplaysFiles(): void {
    $this->drupalLogin($this->viewUser);
    $this->drupalGet('/admin/config/ghi/geojson/AFG/current/list');

    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('File list for AFG (current)');

    // Check that files are listed.
    $assert_session->pageTextContains('AFG_0.geojson');
    $assert_session->pageTextContains('AFG_0.min.geojson');
    $assert_session->pageTextContains('adm1');
    $assert_session->pageTextContains('adm2');
    $assert_session->pageTextContains('adm3');
  }

  /**
   * Tests that archive download works correctly.
   */
  public function testArchiveDownloadWorksCorrectly(): void {
    $this->drupalLogin($this->viewUser);

    // Make request to download endpoint.
    $this->drupalGet('/admin/config/ghi/geojson/AFG/current/download');

    $assert_session = $this->assertSession();
    // Should return a file download response.
    $assert_session->statusCodeEquals(200);
    $assert_session->responseHeaderContains('Content-Type', 'application/zip');
    $assert_session->responseHeaderContains('Content-Disposition', 'attachment');
    $assert_session->responseHeaderContains('Content-Disposition', 'AFG-current.zip');
  }

  /**
   * Tests that directory title shows correct format.
   */
  public function testDirectoryTitleShowsCorrectFormat(): void {
    $this->drupalLogin($this->viewUser);
    $this->drupalGet('/admin/config/ghi/geojson/SYR/2023/list');

    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('File list for SYR (2023)');

    // Test with current version.
    $this->drupalGet('/admin/config/ghi/geojson/IRQ/current/list');
    $assert_session->pageTextContains('File list for IRQ (current)');
  }

  /**
   * Tests that delete version prevents deletion of current version.
   */
  public function testDeleteVersionPreventsCurrentDeletion(): void {
    $this->drupalLogin($this->adminUser);

    // Try to access the deletion of current version - should be restricted by routing.
    $this->drupalGet('/admin/config/ghi/geojson/AFG/current/delete');

    // The form should prevent access when trying to delete current.
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(403);
  }

  /**
   * Tests that archive download handles errors gracefully.
   */
  public function testArchiveDownloadHandlesErrors(): void {
    $this->drupalLogin($this->viewUser);

    // Try to download from non-existent country/version.
    $this->drupalGet('/admin/config/ghi/geojson/XYZ/9999/download');

    $assert_session = $this->assertSession();
    // Should return error response.
    $assert_session->statusCodeEquals(400);
    $assert_session->pageTextContains('There was an error');
  }

  /**
   * Tests that unauthorized access is denied properly.
   */
  public function testUnauthorizedAccessDeniedProperly(): void {
    // Test as anonymous user.
    $this->drupalGet('/admin/config/ghi/geojson');
    $this->assertSession()->statusCodeEquals(403);

    // Test user without permissions.
    $unauthorizedUser = $this->drupalCreateUser(['access administration pages']);
    $this->drupalLogin($unauthorizedUser);
    $this->drupalGet('/admin/config/ghi/geojson');
    $this->assertSession()->statusCodeEquals(403);

    // Test admin-only endpoints with view-only user.
    $this->drupalLogin($this->viewUser);
    $this->drupalGet('/admin/config/ghi/geojson/AFG/2023/delete');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that invalid ISO3 codes are handled gracefully.
   */
  public function testInvalidIso3HandledGracefully(): void {
    $this->drupalLogin($this->viewUser);

    // Test with invalid ISO3 code.
    $this->drupalGet('/admin/config/ghi/geojson/INVALID/current/list');

    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(400);

    // Test download with invalid ISO3.
    $this->drupalGet('/admin/config/ghi/geojson/INVALID/current/download');
    $assert_session->statusCodeEquals(400);
    $assert_session->pageTextContains('There was an error');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Clean up test directories.
    $base_path = 'public://geojson_sources';
    if (file_exists($base_path)) {
      $this->fileSystem->deleteRecursive($base_path);
    }

    parent::tearDown();
  }

}