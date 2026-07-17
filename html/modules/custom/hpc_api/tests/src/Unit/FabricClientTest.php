<?php

namespace Drupal\Tests\hpc_api\Unit;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use Drupal\hpc_api\Query\FabricClient;
use Drupal\hpc_remote_data_cache\RemoteDataCacheInterface;
use Drupal\hpc_remote_data_cache\RemoteDataCacheItem;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ResponseInterface;

/**
 * @covers \Drupal\hpc_api\Query\FabricClient
 *
 * @group HPC API
 */
class FabricClientTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::setContainer(new ContainerBuilder());
  }

  /**
   * Test that a fresh remote data cache hit is returned without HTTP.
   */
  public function testFreshRemoteDataCacheHitAvoidsHttpRequest(): void {
    $payload = (object) [
      'plans' => (object) [
        'items' => [],
      ],
    ];
    $remote_cache = $this->mockRemoteDataCache($this->createRemoteDataCacheItem($payload, 1000, 1200, 1600));

    $client = $this->createFabricClient($this->mockHttpClientThatFailsOnPost(), $remote_cache->reveal());
    $this->assertSame($payload, $client->query('plans { items { Id } }'));
  }

  /**
   * Test that stale remote data is returned and refresh is queued.
   */
  public function testStaleRemoteDataCacheHitQueuesRefresh(): void {
    $payload = (object) [
      'plans' => (object) [
        'items' => [],
      ],
    ];
    $item = $this->createRemoteDataCacheItem($payload, 1000, 900, 1600);
    $remote_cache = $this->mockRemoteDataCache($item);
    $remote_cache->queueRefresh($item)->shouldBeCalledOnce();

    $client = $this->createFabricClient($this->mockHttpClientThatFailsOnPost(), $remote_cache->reveal());
    $this->assertSame($payload, $client->query('plans { items { Id } }'));
  }

  /**
   * Test request timeout options for Fabric GraphQL requests.
   */
  public function testFabricRequestUsesConfiguredTimeouts(): void {
    $http_client = new class() extends Client {

      /**
       * Captured requests.
       *
       * @var array
       */
      public array $requests = [];

      /**
       * {@inheritdoc}
       */
      public function post($uri, array $options = []): ResponseInterface {
        $this->requests[] = [
          'uri' => $uri,
          'options' => $options,
        ];
        return new Response(200, [], '{"data":{"plans":{"items":[]}}}');
      }

    };

    $client = $this->createFabricClientWithAccessToken($http_client);
    $client->disableCache();
    $client->query('plans { items { Id } }');

    $this->assertSame(2, $http_client->requests[0]['options']['connect_timeout']);
    $this->assertSame(8, $http_client->requests[0]['options']['timeout']);
  }

  /**
   * Create a Fabric client under test.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\hpc_remote_data_cache\RemoteDataCacheInterface $remote_cache
   *   The remote data cache.
   *
   * @return \Drupal\hpc_api\Query\FabricClient
   *   The Fabric client.
   */
  private function createFabricClient(ClientInterface $http_client, RemoteDataCacheInterface $remote_cache): FabricClient {
    $config_factory = $this->getConfigFactoryStub([
      'fabric_graphql.settings' => [
        'workspace_id' => 'workspace',
        'endpoint_id' => 'endpoint',
        'host' => 'fabric.example.test',
        'connect_timeout' => 2,
        'timeout' => 8,
      ],
    ]);

    return new FabricClient(
      $config_factory,
      $this->prophesize(LoggerChannelFactoryInterface::class)->reveal(),
      $this->prophesize(KillSwitch::class)->reveal(),
      $http_client,
      $remote_cache,
    );
  }

  /**
   * Create a Fabric client with a test access token.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   *
   * @return \Drupal\hpc_api\Query\FabricClient
   *   The Fabric client.
   */
  private function createFabricClientWithAccessToken(ClientInterface $http_client): FabricClient {
    $config_factory = $this->getConfigFactoryStub([
      'fabric_graphql.settings' => [
        'workspace_id' => 'workspace',
        'endpoint_id' => 'endpoint',
        'host' => 'fabric.example.test',
        'connect_timeout' => 2,
        'timeout' => 8,
      ],
    ]);

    return new class(
      $config_factory,
      $this->prophesize(LoggerChannelFactoryInterface::class)->reveal(),
      $this->prophesize(KillSwitch::class)->reveal(),
      $http_client,
    ) extends FabricClient {

      /**
       * {@inheritdoc}
       */
      public function getAccessToken(): ?string {
        return 'token';
      }

    };
  }

  /**
   * Mock an HTTP client that fails the test if Fabric is called.
   *
   * @return \GuzzleHttp\ClientInterface
   *   The HTTP client test double.
   */
  private function mockHttpClientThatFailsOnPost(): ClientInterface {
    return new class extends Client {

      /**
       * {@inheritdoc}
       */
      public function post($uri, array $options = []): ResponseInterface {
        Assert::fail('Fabric HTTP post must not be called on a remote data cache hit.');
        throw new \LogicException('Unreachable.');
      }

    };
  }

  /**
   * Mock a remote data cache service.
   *
   * @param \Drupal\hpc_remote_data_cache\RemoteDataCacheItem $item
   *   The remote data cache item.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   *   The remote data cache prophecy.
   */
  private function mockRemoteDataCache(RemoteDataCacheItem $item) {
    $remote_cache = $this->prophesize(RemoteDataCacheInterface::class);
    $remote_cache->isEnabled()->willReturn(TRUE);
    $remote_cache->buildCid('fabric_graphql', Argument::type('string'))->willReturn('fabric_graphql:test');
    $remote_cache->get('fabric_graphql:test')->willReturn($item);
    return $remote_cache;
  }

  /**
   * Create a remote data cache item.
   *
   * @param mixed $payload
   *   The cache payload.
   * @param int $request_time
   *   The request time.
   * @param int $fresh_until
   *   The fresh-until timestamp.
   * @param int $stale_until
   *   The stale-until timestamp.
   *
   * @return \Drupal\hpc_remote_data_cache\RemoteDataCacheItem
   *   The remote data cache item.
   */
  private function createRemoteDataCacheItem(mixed $payload, int $request_time, int $fresh_until, int $stale_until): RemoteDataCacheItem {
    return new RemoteDataCacheItem(
      'fabric_graphql:test',
      'fabric_graphql',
      'https://fabric.example.test/graphql',
      '{"query":"query { plans { items { Id } } }"}',
      ['query' => 'query { plans { items { Id } } }'],
      $payload,
      100,
      100,
      100,
      $fresh_until,
      $stale_until,
      FALSE,
      0,
      0,
      100,
      0,
      NULL,
      100,
      $request_time,
    );
  }

}
