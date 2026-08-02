<?php

namespace Drupal\Tests\ghi_geojson\Functional;

use Drupal\Core\File\FileSystemInterface;
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fileSystem = $this->container->get('file_system');

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

    $sources = [
      'AFG' => ['current'],
      'IRQ' => ['current'],
      'SYR' => ['2023'],
    ];

    foreach ($sources as $country => $versions) {
      foreach ($versions as $version) {
        $directory = $base_path . '/' . $country . '/' . $version;
        $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

        foreach ([
          $country . '_0.geojson',
          $country . '_0.min.geojson',
        ] as $file) {
          $filepath = $directory . '/' . $file;
          file_put_contents($filepath, '{"type":"FeatureCollection","features":[]}');
        }

        foreach (['adm1', 'adm2', 'adm3'] as $adm_level) {
          $adm_directory = $directory . '/' . $adm_level;
          $this->fileSystem->prepareDirectory($adm_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
          file_put_contents($adm_directory . '/test.geojson', '{"type":"FeatureCollection","features":[]}');
        }
      }
    }
  }

  /**
   * Tests sources and directory listing pages.
   */
  public function testSourcesAndDirectoryPages(): void {
    $this->drupalLogin($this->viewUser);
    $this->drupalGet('/admin/config/ghi/geojson');

    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('GeoJSON source files');

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
    $assert_session->pageTextContains('current');
    $assert_session->pageTextContains('2023');

    $this->drupalGet('/admin/config/ghi/geojson/AFG/current/list');

    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('File list for AFG (current)');
    $assert_session->pageTextContains('AFG_0.geojson');
    $assert_session->pageTextContains('AFG_0.min.geojson');
    $assert_session->pageTextContains('adm1');
    $assert_session->pageTextContains('adm2');
    $assert_session->pageTextContains('adm3');

    $this->drupalGet('/admin/config/ghi/geojson/SYR/2023/list');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('File list for SYR (2023)');

    $this->drupalGet('/admin/config/ghi/geojson/IRQ/current/list');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('File list for IRQ (current)');
  }

  /**
   * Tests archive downloads and invalid route parameters.
   */
  public function testArchiveDownloadAndErrorResponses(): void {
    $this->drupalLogin($this->viewUser);
    $this->drupalGet('/admin/config/ghi/geojson/AFG/current/download');

    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    $assert_session->responseHeaderContains('Content-Type', 'application/zip');
    $assert_session->responseHeaderContains('Content-Disposition', 'attachment');
    $assert_session->responseHeaderContains('Content-Disposition', 'AFG-current.zip');

    $this->drupalGet('/admin/config/ghi/geojson/XYZ/9999/download');
    $assert_session->statusCodeEquals(400);
    $assert_session->pageTextContains('There was an error');

    $this->drupalGet('/admin/config/ghi/geojson/INVALID/current/list');
    $assert_session->statusCodeEquals(400);

    $this->drupalGet('/admin/config/ghi/geojson/INVALID/current/download');
    $assert_session->statusCodeEquals(400);
    $assert_session->pageTextContains('There was an error');
  }

  /**
   * Tests route access rules.
   */
  public function testAccessRules(): void {
    $this->drupalGet('/admin/config/ghi/geojson');
    $this->assertSession()->statusCodeEquals(403);

    $unauthorizedUser = $this->drupalCreateUser(['access administration pages']);
    $this->drupalLogin($unauthorizedUser);
    $this->drupalGet('/admin/config/ghi/geojson');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->viewUser);
    $this->drupalGet('/admin/config/ghi/geojson/AFG/2023/delete');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/ghi/geojson/AFG/current/delete');
    $this->assertSession()->statusCodeEquals(403);
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
