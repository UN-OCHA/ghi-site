<?php

namespace Drupal\Tests\ghi_geojson\Traits;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Url;
use Prophecy\Argument;

trait GeoJsonTestTrait {

  public function mockGeoJsonService() {
    // Mock the geojson service to avoid dependency on ghi_geojson module.
    $geojson_mock = $this->createMock('\Drupal\ghi_geojson\GeoJson');
    $geojson_mock->method('getGeoJsonSourceFilePath')->willReturn('/test/path/location.geojson');
    $geojson_mock->method('getGeoJsonPublicFilePath')->willReturn('public://test/location.geojson');

    $url = $this->prophesize(Url::class);
    $file_url_generator = $this->prophesize(FileUrlGeneratorInterface::class);
    $file_url_generator->generate(Argument::any())->willReturn($url->reveal());

    try {
      $container = \Drupal::getContainer();
    }
    catch (\Exception $e) {
      $container = new ContainerBuilder();
    }
    $container->set('geojson', $geojson_mock);
    $container->set('file_url_generator', $file_url_generator->reveal());
    \Drupal::setContainer($container);
  }

}