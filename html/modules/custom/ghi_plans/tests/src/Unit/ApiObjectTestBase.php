<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for API objects.
 */
abstract class ApiObjectTestBase extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setupContainer();
  }

  /**
   * Get the content of an ApiObject fixture.
   *
   * @param string $object_type
   *   The object type to look up.
   * @param string $name
   *   The name of the fixture.
   *
   * @return mixed
   *   The json decoded content of the fixture.
   */
  protected function getApiObjectFixture($object_type, $name) {
    return $this->getFixture('ApiObject/' . $object_type, $name);
  }

  /**
   * Get the content of a fixture.
   *
   * @param string $path
   *   The path to the fixture.
   * @param string $name
   *   The name of the fixture.
   *
   * @return mixed
   *   The json decoded content of the fixture.
   */
  protected function getFixture($path, $name) {
    $file_path = $this->root . '/modules/custom/ghi_plans/tests/fixtures/' . $path . '/' . $name . '.json';
    return json_decode(file_get_contents($file_path));
  }

  /**
   * Setup the container with mocked services and stubs.
   */
  private function setupContainer() {
    // Disable endpoint queries during the tests.
    $endpoint_query_manager = $this->getMockBuilder('Drupal\hpc_api\Query\EndpointQueryManager')
      ->disableOriginalConstructor()
      ->getMock();

    // Mock entity loading from storage.
    $entity_storage = $this->getMockBuilder('Drupal\node\NodeStorage')
      ->disableOriginalConstructor()
      ->getMock();
    $entity_storage->method('loadByProperties')->willReturn([]);

    $entity_type_manager = $this->getMockBuilder('Drupal\Core\Entity\EntityTypeManager')
      ->disableOriginalConstructor()
      ->getMock();
    $entity_type_manager->method('getStorage')->willReturn($entity_storage);

    // Mock cache.
    $cache = $this->getMockBuilder('Drupal\Core\Cache\NullBackend')
      ->disableOriginalConstructor()
      ->getMock();

    // Mock time.
    $time = $this->getMockBuilder('Drupal\Component\Datetime\TimeInterface')
      ->disableOriginalConstructor()
      ->getMock();

    // Mock configuration.
    $config_factory = $this->getConfigFactoryStub([
      'hpc_api.settings' => [
        'cache_lifetime' => 3600,
      ],
    ]);

    $container = new ContainerBuilder();
    $container->set('plugin.manager.endpoint_query_manager', $endpoint_query_manager);
    $container->set('entity_type.manager', $entity_type_manager);
    $container->set('cache.default', $cache);
    $container->set('datetime.time', $time);
    $container->set('config.factory', $config_factory);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

}
