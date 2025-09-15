<?php

namespace Drupal\Tests\ghi_geojson\Unit;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_geojson\GeoJson;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the GeoJson service (unit test version).
 *
 * @coversDefaultClass \Drupal\ghi_geojson\GeoJson
 * @group ghi_geojson
 */
class GeoJsonServiceTest extends UnitTestCase {

  use ProphecyTrait;
  use StringTranslationTrait;

  /**
   * The file system service mock.
   *
   * @var \Drupal\Core\File\FileSystemInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $fileSystem;

  /**
   * The GeoJson service under test.
   *
   * @var \Drupal\ghi_geojson\GeoJson
   */
  protected $geoJsonService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fileSystem = $this->prophesize(FileSystemInterface::class);

    $this->geoJsonService = new GeoJson(
      $this->fileSystem->reveal()
    );
  }

  /**
   * Tests the constructor.
   *
   * @covers ::__construct
   */
  public function testConstruct() {
    $this->assertInstanceOf(GeoJson::class, $this->geoJsonService);
    $this->assertInstanceOf(FileSystemInterface::class, $this->geoJsonService->fileSystem);
  }

  /**
   * Tests getSourceDirectoryPath method.
   *
   * @covers ::getSourceDirectoryPath
   */
  public function testGetSourceDirectoryPath() {
    $result = $this->geoJsonService->getSourceDirectoryPath('AFG', '2022');
    $expected = GeoJson::GEOJSON_SOURCE_DIR . '/AFG/2022';
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests getSourceDirectoryPath method with NULL version.
   *
   * @covers ::getSourceDirectoryPath
   */
  public function testGetSourceDirectoryPathWithNullVersion() {
    $result = $this->geoJsonService->getSourceDirectoryPath('AFG', NULL);
    $expected = GeoJson::GEOJSON_SOURCE_DIR . '/AFG/';
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests getIsoCodes method with mocked file system.
   *
   * @covers ::getIsoCodes
   */
  public function testGetIsoCodesWithMockedFileSystem() {
    $directories = [
      'AFG' => (object) ['filename' => 'AFG'],
      'SYR' => (object) ['filename' => 'SYR'],
      'IRQ' => (object) ['filename' => 'IRQ'],
    ];

    $this->fileSystem->scanDirectory(
      GeoJson::GEOJSON_SOURCE_DIR,
      '/.*/',
      ['recurse' => FALSE]
    )->willReturn($directories);

    $result = $this->geoJsonService->getIsoCodes();
    $this->assertIsArray($result);
    $this->assertCount(3, $result);
    $this->assertContains('AFG', $result);
    $this->assertContains('SYR', $result);
    $this->assertContains('IRQ', $result);
  }

  /**
   * Tests getVersionsForIsoCode method.
   *
   * @covers ::getVersionsForIsoCode
   */
  public function testGetVersionsForIsoCode() {
    $version_directories = [
      '2021' => (object) ['filename' => '2021'],
      '2022' => (object) ['filename' => '2022'],
      '2023' => (object) ['filename' => '2023'],
    ];

    $this->fileSystem->scanDirectory(
      GeoJson::GEOJSON_SOURCE_DIR . '/AFG',
      '/.*/',
      ['recurse' => FALSE]
    )->willReturn($version_directories);

    $result = $this->geoJsonService->getVersionsForIsoCode('AFG');
    $expected = ['2023', '2022', '2021']; // Reversed order
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests getVersionsForIsoCode with empty result.
   *
   * @covers ::getVersionsForIsoCode
   */
  public function testGetVersionsForIsoCodeEmpty() {
    $this->fileSystem->scanDirectory(
      GeoJson::GEOJSON_SOURCE_DIR . '/INVALID',
      '/.*/',
      ['recurse' => FALSE]
    )->willReturn([]);

    $result = $this->geoJsonService->getVersionsForIsoCode('INVALID');
    $this->assertEquals([], $result);
  }

  /**
   * Tests getFiles method basic behavior.
   *
   * @covers ::getFiles
   */
  public function testGetFilesBasicBehavior() {
    // Mock scanDirectory to return files
    $files = [
      'file1.geojson' => (object) ['filename' => 'file1.geojson'],
      'file2.geojson' => (object) ['filename' => 'file2.geojson'],
    ];

    $this->fileSystem->scanDirectory(
      'public://test',
      '/.*/',
      ['recurse' => FALSE]
    )->willReturn($files);

    $result = $this->geoJsonService->getFiles('public://test');
    $this->assertIsArray($result);
    $this->assertCount(2, $result);
  }

  /**
   * Tests getFiles method with pattern.
   *
   * @covers ::getFiles
   */
  public function testGetFilesWithPattern() {
    $files = [
      'file1.geojson' => (object) ['filename' => 'file1.geojson'],
    ];

    $this->fileSystem->scanDirectory(
      'public://test',
      '/.*\.geojson$/',
      ['recurse' => FALSE]
    )->willReturn($files);

    $result = $this->geoJsonService->getFiles('public://test', '/.*\.geojson$/');
    $this->assertIsArray($result);
    $this->assertCount(1, $result);
  }

  /**
   * Tests getExpectedFilenamesForCountry method.
   *
   * @covers ::getExpectedFilenamesForCountry
   */
  public function testGetExpectedFilenamesForCountry() {
    $result = $this->geoJsonService->getExpectedFilenamesForCountry('AFG');
    $this->assertIsArray($result);
    $this->assertNotEmpty($result);

    // Should contain expected files based on actual implementation
    $this->assertContains('AFG_0.geojson', $result);
    $this->assertContains('AFG_0.min.geojson', $result);
    $this->assertContains('adm1', $result);
    $this->assertContains('adm2', $result);
    $this->assertContains('adm3', $result);
  }

  /**
   * Tests getCacheTags method.
   *
   * @covers ::getCacheTags
   */
  public function testGetCacheTags() {
    $result = $this->geoJsonService->getCacheTags('AFG', '2022');
    $this->assertIsArray($result);
    $this->assertContains('ghi_geojson:geojson-AFG', $result);
    $this->assertContains('ghi_geojson:geojson-AFG-2022', $result);
  }

  /**
   * Tests getCacheTags method without version.
   *
   * @covers ::getCacheTags
   */
  public function testGetCacheTagsWithoutVersion() {
    $result = $this->geoJsonService->getCacheTags('AFG');
    $this->assertIsArray($result);
    $this->assertContains('ghi_geojson:geojson-AFG', $result);
  }

  /**
   * Tests constants.
   */
  public function testConstants() {
    $this->assertEquals('public://geojson_sources', GeoJson::GEOJSON_SOURCE_DIR);
    $this->assertEquals('public://geojson', GeoJson::GEOJSON_DIR);
    $this->assertEquals('temporary://geojson', GeoJson::ARCHIVE_TEMP_DIR);
  }

  /**
   * Tests method parameter validation.
   */
  public function testMethodParameterValidation() {
    // Test with different types of parameters
    $result1 = $this->geoJsonService->getSourceDirectoryPath('AFG', '2022');
    $result2 = $this->geoJsonService->getSourceDirectoryPath('SYR', '2023');

    $this->assertNotEquals($result1, $result2);
    $this->assertStringContainsString('AFG', $result1);
    $this->assertStringContainsString('SYR', $result2);
  }

  /**
   * Tests buildGeoJsonSourceFilePath method for admin level 0 (countries).
   *
   * @covers ::buildGeoJsonSourceFilePath
   */
  public function testBuildGeoJsonSourceFilePathCountryLevel() {
    // Create a mock location for admin level 0 (country)
    $location = $this->prophesize(\Drupal\ghi_geojson\GeoJsonLocationInterface::class);
    $location->getIso3()->willReturn('AFG');
    $location->getGeoJsonVersion()->willReturn('2022');
    $location->getAdminLevel()->willReturn(0);
    $location->getPcode()->willReturn(NULL);

    // Use reflection to test the private method
    $reflection = new \ReflectionClass($this->geoJsonService);
    $method = $reflection->getMethod('buildGeoJsonSourceFilePath');
    $method->setAccessible(TRUE);

    // Test minified version
    $result = $method->invoke($this->geoJsonService, $location->reveal(), '2022', TRUE);
    $this->assertEquals('2022/AFG_0.min.geojson', $result);

    // Test non-minified version
    $result = $method->invoke($this->geoJsonService, $location->reveal(), '2022', FALSE);
    $this->assertEquals('2022/AFG_0.geojson', $result);
  }

  /**
   * Tests buildGeoJsonSourceFilePath method for admin levels 1+.
   *
   * @covers ::buildGeoJsonSourceFilePath
   */
  public function testBuildGeoJsonSourceFilePathAdminLevel() {
    // Create a mock location for admin level 1
    $location = $this->prophesize(\Drupal\ghi_geojson\GeoJsonLocationInterface::class);
    $location->getIso3()->willReturn('SYR');
    $location->getGeoJsonVersion()->willReturn('2023');
    $location->getAdminLevel()->willReturn(1);
    $location->getPcode()->willReturn('SY01');

    // Use reflection to test the private method
    $reflection = new \ReflectionClass($this->geoJsonService);
    $method = $reflection->getMethod('buildGeoJsonSourceFilePath');
    $method->setAccessible(TRUE);

    // Test minified version
    $result = $method->invoke($this->geoJsonService, $location->reveal(), '2023', TRUE);
    $this->assertEquals('2023/adm1/SY01.min.geojson', $result);

    // Test non-minified version
    $result = $method->invoke($this->geoJsonService, $location->reveal(), '2023', FALSE);
    $this->assertEquals('2023/adm1/SY01.geojson', $result);
  }

  /**
   * Tests buildGeoJsonSourceFilePath method for admin level 2.
   *
   * @covers ::buildGeoJsonSourceFilePath
   */
  public function testBuildGeoJsonSourceFilePathAdminLevel2() {
    // Create a mock location for admin level 2
    $location = $this->prophesize(\Drupal\ghi_geojson\GeoJsonLocationInterface::class);
    $location->getIso3()->willReturn('IRQ');
    $location->getGeoJsonVersion()->willReturn('2021');
    $location->getAdminLevel()->willReturn(2);
    $location->getPcode()->willReturn('IQ0101');

    // Use reflection to test the private method
    $reflection = new \ReflectionClass($this->geoJsonService);
    $method = $reflection->getMethod('buildGeoJsonSourceFilePath');
    $method->setAccessible(TRUE);

    // Test with version parameter
    $result = $method->invoke($this->geoJsonService, $location->reveal(), '2021', TRUE);
    $this->assertEquals('2021/adm2/IQ0101.min.geojson', $result);
  }

  /**
   * Tests buildGeoJsonSourceFilePath method with invalid parameters.
   *
   * @covers ::buildGeoJsonSourceFilePath
   */
  public function testBuildGeoJsonSourceFilePathInvalidParameters() {
    // Test with empty ISO3
    $location = $this->prophesize(\Drupal\ghi_geojson\GeoJsonLocationInterface::class);
    $location->getIso3()->willReturn('');
    $location->getGeoJsonVersion()->willReturn('2022');

    $reflection = new \ReflectionClass($this->geoJsonService);
    $method = $reflection->getMethod('buildGeoJsonSourceFilePath');
    $method->setAccessible(TRUE);

    $result = $method->invoke($this->geoJsonService, $location->reveal(), '2022', TRUE);
    $this->assertNull($result);

    // Test admin level without pcode
    $location2 = $this->prophesize(\Drupal\ghi_geojson\GeoJsonLocationInterface::class);
    $location2->getIso3()->willReturn('AFG');
    $location2->getGeoJsonVersion()->willReturn('2022');
    $location2->getAdminLevel()->willReturn(1);
    $location2->getPcode()->willReturn('');

    $result2 = $method->invoke($this->geoJsonService, $location2->reveal(), '2022', TRUE);
    $this->assertNull($result2);
  }

  /**
   * Tests buildGeoJsonSourceFilePath method using location's default version.
   *
   * @covers ::buildGeoJsonSourceFilePath
   */
  public function testBuildGeoJsonSourceFilePathDefaultVersion() {
    // Create a mock location that provides its own version
    $location = $this->prophesize(\Drupal\ghi_geojson\GeoJsonLocationInterface::class);
    $location->getIso3()->willReturn('AFG');
    $location->getGeoJsonVersion()->willReturn('2024');
    $location->getAdminLevel()->willReturn(0);
    $location->getPcode()->willReturn(NULL);

    // Use reflection to test the private method
    $reflection = new \ReflectionClass($this->geoJsonService);
    $method = $reflection->getMethod('buildGeoJsonSourceFilePath');
    $method->setAccessible(TRUE);

    // Test with NULL version (should use location's default version)
    $result = $method->invoke($this->geoJsonService, $location->reveal(), NULL, TRUE);
    $this->assertEquals('2024/AFG_0.min.geojson', $result);
  }

}