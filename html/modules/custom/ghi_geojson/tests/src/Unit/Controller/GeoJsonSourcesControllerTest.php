<?php

namespace Drupal\Tests\ghi_geojson\Unit\Controller;

use Drupal\Component\DependencyInjection\Container;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\File\FileSystemInterface;
use Drupal\ghi_geojson\Controller\GeoJsonSourcesController;
use Drupal\ghi_geojson\GeoJson;
use Drupal\ghi_geojson\GeoJsonDirectoryList;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unit tests for the GeoJsonSourcesController.
 *
 * @coversDefaultClass \Drupal\ghi_geojson\Controller\GeoJsonSourcesController
 * @group ghi_geojson
 */
class GeoJsonSourcesControllerTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The controller under test.
   *
   * @var \Drupal\ghi_geojson\Controller\GeoJsonSourcesController
   */
  protected $controller;

  /**
   * Mock file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $fileSystem;

  /**
   * Mock GeoJson service.
   *
   * @var \Drupal\ghi_geojson\GeoJson|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $geoJson;

  /**
   * Mock GeoJsonDirectoryList service.
   *
   * @var \Drupal\ghi_geojson\GeoJsonDirectoryList|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $geoJsonDirectoryList;

  /**
   * Mock the modal config.
   *
   * @var \Drupal\Core\Config\Config|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $modalConfig;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create mock services.
    $file_system = $this->prophesize(FileSystemInterface::class);
    $geojson = $this->prophesize(GeoJson::class);
    $geojson->getFiles('public://geojson_sources', '/^[A-Z][A-Z][A-Z]$/')->willReturn([]);
    $geojson_directory_listing = $this->prophesize(GeoJsonDirectoryList::class);
    $modal_config = $this->prophesize(Config::class);
    $modal_config->get('modal_width')->willReturn('80%');
    $modal_config->get('modal_height')->willReturn('auto');
    $modal_config->get('modal_autoresize')->willReturn(TRUE);
    $modal_config->get('theme_display')->willReturn('default_theme');

    $string_translation = $this->getStringTranslationStub();
    $config_factory = $this->prophesize(ConfigFactory::class);
    $config_factory->get('layout_builder_modal.settings')->willReturn($modal_config->reveal());

    $container = new Container();
    $container->set('file_system', $file_system->reveal());
    $container->set('geojson', $geojson->reveal());
    $container->set('geojson.directory_list', $geojson_directory_listing->reveal());
    $container->set('config.factory', $config_factory->reveal());
    $container->set('string_translation', $string_translation);
    \Drupal::setContainer($container);

    $this->controller = GeoJsonSourcesController::create($container);
  }

  /**
   * Tests controller instantiation with all required services.
   *
   * @covers ::create
   */
  public function testControllerConstruction(): void {
    $this->assertInstanceOf(GeoJsonSourcesController::class, $this->controller);

    // Use reflection to verify properties are set.
    $reflection = new \ReflectionClass($this->controller);

    $fileSystemProperty = $reflection->getProperty('fileSystem');
    $fileSystemProperty->setAccessible(TRUE);
    $this->assertInstanceOf(FileSystemInterface::class, $fileSystemProperty->getValue($this->controller));

    $geoJsonProperty = $reflection->getProperty('geojson');
    $geoJsonProperty->setAccessible(TRUE);
    $this->assertInstanceOf(GeoJson::class, $geoJsonProperty->getValue($this->controller));

    $directoryListProperty = $reflection->getProperty('geojsonDirectoryList');
    $directoryListProperty->setAccessible(TRUE);
    $this->assertInstanceOf(GeoJsonDirectoryList::class, $directoryListProperty->getValue($this->controller));

    $modalConfigProperty = $reflection->getProperty('modalConfig');
    $modalConfigProperty->setAccessible(TRUE);
    $this->assertInstanceOf(Config::class, $modalConfigProperty->getValue($this->controller));
  }

  /**
   * Tests directoryTitle method with basic parameters.
   *
   * @covers ::directoryTitle
   */
  public function testDirectoryTitle(): void {
    // Test with normal version.
    $result = $this->controller->directoryTitle('AFG', '2022');
    $this->assertEquals('File list for AFG (2022)', $result);

    // Test with current version.
    $result = $this->controller->directoryTitle('SYR', 'current');
    $this->assertEquals('File list for SYR (current)', $result);

    // Test with different ISO codes.
    $result = $this->controller->directoryTitle('IRQ', '2023');
    $this->assertEquals('File list for IRQ (2023)', $result);
  }

  /**
   * Tests directoryTitle method with edge case parameters.
   *
   * @covers ::directoryTitle
   */
  public function testDirectoryTitleEdgeCases(): void {
    // Test with empty strings.
    $result = $this->controller->directoryTitle('', '');
    $this->assertEquals('File list for  ()', $result);

    // Test with special characters.
    $result = $this->controller->directoryTitle('ABC', 'v1.0-beta');
    $this->assertEquals('File list for ABC (v1.0-beta)', $result);

    // Test with numeric version.
    $result = $this->controller->directoryTitle('XYZ', '123');
    $this->assertEquals('File list for XYZ (123)', $result);
  }

  /**
   * Tests sourcesPage method with no data.
   *
   * @covers ::sourcesPage
   */
  public function testSourcesPageNoData(): void {
    $result = $this->controller->sourcesPage();

    // Should still return render array.
    $this->assertIsArray($result);
    $this->assertArrayHasKey('#type', $result);
    $this->assertEquals('table', $result['#type']);

    // Should have empty rows.
    $this->assertArrayHasKey('#rows', $result);
    $this->assertEmpty($result['#rows']);
  }

}