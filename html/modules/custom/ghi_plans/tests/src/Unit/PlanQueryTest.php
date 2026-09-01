<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Cache\NullBackend;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\ghi_plans\ApiObjects\Plan;
use Drupal\ghi_plans\Plugin\FabricQuery\PlanQuery;
use Drupal\hpc_api\ObjectStore;
use Drupal\hpc_api\Query\FabricClient;
use Drupal\hpc_api\Query\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryManager;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the plan Fabric query plugin.
 *
 * @group ghi_plans
 *
 * @coversDefaultClass \Drupal\ghi_plans\Plugin\FabricQuery\PlanQuery
 */
class PlanQueryTest extends UnitTestCase {

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\NullBackend
   */
  private NullBackend $cache;

  /**
   * The object store.
   *
   * @var \Drupal\hpc_api\ObjectStore
   */
  private ObjectStore $objectStore;

  /**
   * The mocked Fabric client.
   *
   * @var \Drupal\hpc_api\Query\FabricClient|\PHPUnit\Framework\MockObject\MockObject
   */
  private FabricClient $fabricClient;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $time = new Time();
    $this->cache = new NullBackend('default');
    $this->objectStore = new ObjectStore();
    $this->fabricClient = $this->createMock(FabricClient::class);
    $this->fabricClient->method('createQuery')
      ->willReturnCallback(fn(string $query_name, mixed $items = NULL, ?array $filters = NULL, ?int $limit = NULL): FabricQuery => new FabricQuery($query_name, $items, $filters, $limit));

    $fabric_query_manager = $this->createMock(FabricQueryManager::class);
    $fabric_query_manager->method('hasDefinition')->willReturn(FALSE);

    $container = new ContainerBuilder();
    $container->set('cache.default', $this->cache);
    $container->set('datetime.time', $time);
    $container->set('hpc_api.fabric_client', $this->fabricClient);
    $container->set('plugin.manager.fabric_query_manager', $fabric_query_manager);
    $container->set('config.factory', $this->getConfigFactoryStub([
      'hpc_api.settings' => [
        'cache_lifetime' => 3600,
      ],
    ]));
    \Drupal::setContainer($container);

    drupal_static_reset();
  }

  /**
   * Tests that year collections include plans already loaded by ID.
   *
   * @covers ::getPlansById
   * @covers ::getPlansByYear
   */
  public function testGetPlansByYearIncludesCachedPlans(): void {
    $this->fabricClient->method('execute')
      ->willReturn([
        1263 => (object) ['Id' => 1263],
        2001 => (object) ['Id' => 2001],
        2002 => (object) ['Id' => 2002],
      ]);
    $this->fabricClient->method('executeMultiple')
      ->willReturnCallback(function (array $queries): array {
        $query_string = implode(' ', array_map(fn(FabricQuery $query): string => $query->toString(), $queries));
        $plan_ids = str_contains($query_string, '1263') ? [1263] : [2001, 2002];
        $plans = [];
        foreach ($plan_ids as $plan_id) {
          $plans[$plan_id] = $this->mockPlanRawData($plan_id);
        }
        return [
          'plans' => $plans,
          'planReportingPeriods' => [],
        ];
      });

    $plan_query = $this->createPlanQuery();
    $cached_plans = $plan_query->getPlansById([1263]);
    $this->assertArrayHasKey(1263, $cached_plans);
    $this->assertArrayHasKey(1263, $this->objectStore->getObjects([1263], Plan::getObjectStorageKey()));

    $plans = $plan_query->getPlansByYear(2025);
    $this->assertSame([1263, 2001, 2002], array_keys($plans));

    $cached_collection = $this->objectStore->getObjectCollection(Plan::getObjectStorageKey(), 'year', 2025);
    $this->assertSame([1263, 2001, 2002], array_keys($cached_collection));
  }

  /**
   * Create a plan query instance with mocked dependencies.
   *
   * @return \Drupal\ghi_plans\Plugin\FabricQuery\PlanQuery
   *   A plan query instance.
   */
  private function createPlanQuery(): PlanQuery {
    $plan_query = new PlanQuery([], 'plan', ['id' => 'plan']);
    $reflection = new \ReflectionObject($plan_query);
    foreach ([
      'fabricClient' => $this->fabricClient,
      'objectStore' => $this->objectStore,
    ] as $property_name => $value) {
      $property = $reflection->getProperty($property_name);
      $property->setValue($plan_query, $value);
    }
    return $plan_query;
  }

  /**
   * Create raw Fabric data for a plan.
   *
   * @param int $plan_id
   *   The plan id.
   *
   * @return object
   *   A raw Fabric plan object.
   */
  private function mockPlanRawData(int $plan_id): object {
    return (object) [
      'Id' => $plan_id,
      'Name' => 'Plan ' . $plan_id,
      'ShortName' => NULL,
      'PlanSubTitle' => NULL,
      'PlanType' => NULL,
      'PlanCosting' => NULL,
      'PlanLanguageCode' => 'en',
      'PlanClusterType' => NULL,
      'StartDate' => NULL,
      'EndDate' => NULL,
      'CreatedAt' => NULL,
      'UpdatedAt' => NULL,
      'IsReleased' => TRUE,
      'IsRestricted' => FALSE,
      'IsPartOfGHO' => TRUE,
      'DocumentPublishDate' => NULL,
      'Description' => NULL,
      'FocusedLocationName' => NULL,
      'FocusedLocationId' => NULL,
      'CurrentReportingPeriodId' => NULL,
      'LastPublishedReportingPeriodId' => NULL,
      'IsLegacyCurrentVersion' => TRUE,
      'period' => (object) [
        'items' => [
          (object) [
            'CalendarYear' => 2025,
          ],
        ],
      ],
      'location' => (object) [
        'items' => [],
      ],
      'organization' => (object) [
        'items' => [],
      ],
    ];
  }

}
