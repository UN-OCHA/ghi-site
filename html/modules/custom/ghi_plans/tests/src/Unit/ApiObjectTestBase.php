<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\ghi_base_objects\ApiObjects\Location;
use Drupal\ghi_base_objects\Plugin\FabricQuery\LocationQuery;
use Drupal\ghi_geojson\GeoJson;
use Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\ApiObjects\Prototypes\EntityPrototype;
use Drupal\ghi_plans\Helpers\PlanEntityHelper;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentPrototypeQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\PlanQuery;
use Drupal\hpc_api\ApiObjects\Types\MetricType;
use Drupal\hpc_api\ApiObjects\Types\Unit;
use Drupal\hpc_api\Plugin\FabricQuery\EntityTypeQuery;
use Drupal\hpc_api\Query\FabricQueryBase;
use Drupal\hpc_common\Helpers\StringHelper;
use Drupal\Tests\ghi_base_objects\Unit\ApiBaseObjectTest;
use Prophecy\Argument;

/**
 * Tests for API objects.
 */
abstract class ApiObjectTestBase extends ApiBaseObjectTest {

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
   *   The json decoded content of the fixture or FALSE on failure.
   */
  protected function getApiObjectFixture($object_type, $name) {
    return $this->getFixture('ApiObject/' . $object_type, $name);
  }

  /**
   * Load an entity from the fixtures.
   *
   * @param string $type
   *   The type of the attachment.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface
   *   The entity object.
   */
  protected function getEntityFromFixture($type): ?EntityObjectInterface {
    $entity_data = $this->getApiObjectFixture('Entities', $type);
    $this->assertNotEmpty($entity_data);
    $entity = PlanEntityHelper::getObject($entity_data);

    // Set the attachment prototype to prevent exceptions.
    $prototype = $this->getApiObjectFixture('EntityPrototype', $entity_data->HpcEntityPrototypeId);
    $entity_prototype = new EntityPrototype($prototype);
    (new \ReflectionClass($entity::class))->getProperty('prototype')->setValue($entity, $entity_prototype);
    return $entity;
  }

  /**
   * Load an entity prototype from the fixtures.
   *
   * @param string $type
   *   The type of the entity prototype.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Prototypes\EntityPrototype
   *   The entity prototype object.
   */
  protected function getEntityPrototypeFromFixture($type): EntityPrototype {
    $data = $this->getApiObjectFixture('EntityPrototype', $type);
    $this->assertNotEmpty($data);
    return new EntityPrototype($data);
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
   *   The json decoded content of the fixture or FALSE on failure.
   */
  protected function getFixture($path, $name) {
    $file_path = $this->root . '/modules/custom/ghi_plans/tests/fixtures/' . $path . '/' . $name . '.json';
    return file_exists($file_path) ? json_decode(file_get_contents($file_path)) : FALSE;
  }

  /**
   * Mock a metric type.
   *
   * @param int $id
   *   The id of the metric type.
   * @param string $type
   *   The type of the metric type.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\MetricType
   *   A metric type object.
   */
  protected function mockMetricType(int $id, string $type) {
    return new MetricType((object) [
      'Id' => $id,
      'Name' => ucfirst(str_replace('_', ' ', StringHelper::camelCaseToUnderscoreCase($type))),
      'HPCType' => $type,
    ]);
  }

  /**
   * Setup the container with mocked services and stubs.
   */
  private function setupContainer() {
    // Disable endpoint queries during the tests.
    $endpoint_query_manager = $this->prophesize('Drupal\hpc_api\Query\EndpointQueryManager');

    // Setup fabric queries during the tests.
    $fabric_query_manager = $this->prophesize('Drupal\hpc_api\Query\FabricQueryManager');

    // But mock loading of the metric types.
    $metric_types = [
      $this->mockMetricType(1, 'totalPopulation'),
      $this->mockMetricType(2, 'affected'),
      $this->mockMetricType(3, 'inNeed'),
      $this->mockMetricType(5, 'target'),
      $this->mockMetricType(10, 'requirements'),
      $this->mockMetricType(14, 'cumulativeReach'),
      $this->mockMetricType(15, 'optionOverallCumulReach'),
      $this->mockMetricType(16, 'periodicalReach'),
      $this->mockMetricType(17, 'optionOverallPeriodicalReach'),
      $this->mockMetricType(20, 'covered'),
    ];
    $entity_type_query = $this->prophesize(EntityTypeQuery::class);
    foreach ($metric_types as $metric_type) {
      $entity_type_query->getMetricType($metric_type->id())->willReturn($metric_type);
    }
    $entity_type_query->getMetricTypes()->willReturn($metric_types);
    $entity_type_query->getUnit(Argument::any())->willReturn($this->prophesize(Unit::class)->reveal());

    $location_query = $this->prophesize(LocationQuery::class);
    $location_query->getLocationsById(Argument::any())->will(function ($arguments) {
      $locations = [];
      foreach ($arguments[0] as $location_id) {
        $locations[$location_id] = new Location((object) [
          'Id' => $location_id,
          'Name' => 'Location name',
          'AdminLevel' => rand(1, 3),
          'CountryId' => 36,
          'Latitude' => 0,
          'Longitude' => 0,
        ]);
      }
      return $locations;
    });

    $plan_query = $this->prophesize(PlanQuery::class);
    $plan_query->getPlanReportingPeriods(Argument::any())->willReturn([]);

    $fabric_query_manager->hasDefinition(Argument::any())->willReturn(TRUE);
    $fabric_query_manager->createInstance('entity_type')->willReturn($entity_type_query->reveal());
    $fabric_query_manager->createInstance('plan')->willReturn($plan_query->reveal());
    $fabric_query_manager->createInstance('location')->willReturn($location_query->reveal());
    $fabric_query_manager->createInstance(Argument::any())->willReturn($this->prophesize(FabricQueryBase::class)->reveal());

    $attachment_prototype_query = $this->prophesize(AttachmentPrototypeQuery::class);
    foreach ([5443] as $id) {
      $prototype = $this->getApiObjectFixture('AttachmentPrototype', $id);
      $attachment_prototype_query->getPrototype($id)->willReturn(new AttachmentPrototype($prototype));
      $attachment_prototype_query->getPrototypeByPlanAndId(Argument::any(), $id)->willReturn(NULL);
    }
    $fabric_query_manager->createInstance('attachment_prototype')->willReturn($attachment_prototype_query->reveal());

    // Mock entity loading from storage.
    $node_storage = $this->prophesize('Drupal\node\NodeStorage');
    $node_storage->loadByProperties(Argument::any())->willReturn([]);
    $entity_storage = $this->prophesize(EntityStorageInterface::class);
    $entity_storage->loadByProperties(Argument::any())->willReturn([]);

    $entity_type_manager = $this->prophesize('Drupal\Core\Entity\EntityTypeManager');
    $entity_type_manager->getStorage('node')->willReturn($node_storage->reveal());
    $entity_type_manager->getStorage('base_object')->willReturn($entity_storage->reveal());

    // Mock cache.
    $cache = $this->prophesize('Drupal\Core\Cache\NullBackend');

    // Mock time.
    $time = $this->prophesize('Drupal\Component\Datetime\TimeInterface');

    // Mock configuration.
    $config_factory = $this->getConfigFactoryStub([
      'hpc_api.settings' => [
        'cache_lifetime' => 3600,
      ],
    ]);

    // Mock twig.
    $twig = $this->prophesize('Drupal\Core\Template\TwigEnvironment');

    // Mock renderer.
    $renderer = $this->prophesize('Drupal\Core\Render\RendererInterface');

    $container = new ContainerBuilder();
    $container->set('plugin.manager.endpoint_query_manager', $endpoint_query_manager->reveal());
    $container->set('plugin.manager.fabric_query_manager', $fabric_query_manager->reveal());
    $container->set('entity_type.manager', $entity_type_manager->reveal());
    $container->set('cache.default', $cache->reveal());
    $container->set('datetime.time', $time->reveal());
    $container->set('config.factory', $config_factory);
    $container->set('twig', $twig->reveal());
    $container->set('renderer', $renderer->reveal());
    $container->set('string_translation', $this->getStringTranslationStub());
    $container->set('geojson', $this->prophesize(GeoJson::class)->reveal());
    \Drupal::setContainer($container);
  }

  /**
   * Disable all fabric queries for the rest of the test.
   */
  protected function disableFabricQueries() {
    $fabric_query_manager = $this->prophesize('Drupal\hpc_api\Query\FabricQueryManager');
    $container = \Drupal::getContainer();
    $container->set('plugin.manager.fabric_query_manager', $fabric_query_manager->reveal());
    \Drupal::setContainer($container);
    drupal_static_reset();
  }

}
