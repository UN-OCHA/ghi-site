<?php

namespace Drupal\Tests\ghi_content\Unit\RemoteSource;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\Plugin\RemoteSource\HpcContentModule;
use Drupal\ghi_content\RemoteResponse\RemoteResponse;
use Drupal\Tests\UnitTestCase;
use Drupal\hpc_remote_data_cache\RemoteDataCacheInterface;
use Drupal\hpc_remote_data_cache\RemoteDataCacheItem;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the HPC content module remote source.
 *
 * @group ghi_content
 */
class HpcContentModuleTest extends UnitTestCase {

  /**
   * Tests that stable GraphQL requests can be served from remote data cache.
   */
  public function testQueryUsesRemoteDataCacheHit(): void {
    $payload = '{
      article(id:123) {
        id
      }
    }';
    $response = new RemoteResponse((object) [
      'article' => (object) [
        'id' => 123,
      ],
    ], 200);
    $remote_cache = $this->createMock(RemoteDataCacheInterface::class);
    $remote_cache->expects($this->once())
      ->method('isEnabled')
      ->willReturn(TRUE);
    $remote_cache->expects($this->once())
      ->method('buildCid')
      ->with('hpc_content_module_graphql', $this->isType('string'))
      ->willReturn('hpc_content_module_graphql:test');
    $remote_cache->expects($this->once())
      ->method('get')
      ->with('hpc_content_module_graphql:test')
      ->willReturn($this->createRemoteDataCacheItem($response));

    $remote_source = $this->createRemoteSource([], [], $this->mockHttpClientThatFailsOnPost(), $remote_cache);
    $this->assertSame($response, $remote_source->query($payload));
  }

  /**
   * Tests that remote refresh settings use runtime config overrides.
   */
  public function testRemoteRefreshSettingsUseRuntimeOverrides(): void {
    $remote_source = $this->createRemoteSource([
      'webhook_secret' => 'runtime-secret',
      'signature_ttl' => 120,
      'max_body_size' => 2048,
    ]);

    $this->assertSame('runtime-secret', $remote_source->getRemoteRefreshWebhookSecret());
    $this->assertSame(120, $remote_source->getRemoteRefreshSignatureTtl());
    $this->assertSame(2048, $remote_source->getRemoteRefreshMaxBodySize());
  }

  /**
   * Tests that an empty configured secret keeps the stored secret.
   */
  public function testSetConfigurationPreservesStoredSecretWhenSubmittedSecretIsEmpty(): void {
    $remote_source = $this->createRemoteSource([
      'webhook_secret' => 'runtime-secret',
      'signature_ttl' => 120,
      'max_body_size' => 2048,
    ]);

    $remote_source->setConfiguration([
      'remote_refresh' => [
        'webhook_secret' => '',
        'signature_ttl' => 300,
        'max_body_size' => 4096,
      ],
    ]);

    $configuration = $remote_source->getConfiguration();
    $this->assertSame('stored-secret', $configuration['remote_refresh']['webhook_secret']);
  }

  /**
   * Creates a remote source with mocked config.
   *
   * @param array $runtime_refresh_settings
   *   Runtime remote refresh settings.
   * @param string[] $overridden_refresh_settings
   *   Overridden remote refresh settings.
   * @param \GuzzleHttp\ClientInterface|null $http_client
   *   The HTTP client.
   * @param \Drupal\hpc_remote_data_cache\RemoteDataCacheInterface|null $remote_cache
   *   The remote data cache.
   *
   * @return \Drupal\ghi_content\Plugin\RemoteSource\HpcContentModule
   *   The remote source plugin.
   */
  private function createRemoteSource(array $runtime_refresh_settings = [], array $overridden_refresh_settings = [], ?ClientInterface $http_client = NULL, ?RemoteDataCacheInterface $remote_cache = NULL): HpcContentModule {
    $stored_configuration = [
      'base_url' => 'https://content.example.org',
      'endpoint' => 'ncms',
      'access_key' => NULL,
      'remote_refresh' => [
        'webhook_secret' => 'stored-secret',
        'signature_ttl' => 300,
        'max_body_size' => 4096,
      ],
    ];

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('getOriginal')
      ->with('hpc_content_module')
      ->willReturn($stored_configuration);
    $config->method('hasOverrides')
      ->willReturnCallback(function (string $key) use ($overridden_refresh_settings) {
        $prefix = 'hpc_content_module.remote_refresh.';
        if (strpos($key, $prefix) === 0) {
          return in_array(substr($key, strlen($prefix)), $overridden_refresh_settings, TRUE);
        }
        return FALSE;
      });
    $config->method('get')
      ->willReturnCallback(function (string $key) use ($runtime_refresh_settings) {
        $prefix = 'hpc_content_module.remote_refresh.';
        if (strpos($key, $prefix) === 0) {
          return $runtime_refresh_settings[substr($key, strlen($prefix))] ?? NULL;
        }
        return NULL;
      });

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
      ->with('ghi_content.remote_sources')
      ->willReturn($config);

    $remote_source = new HpcContentModule(
      [],
      'hpc_content_module',
      [
        'id' => 'hpc_content_module',
        'label' => 'HPC Content Module',
      ],
      $http_client ?? $this->createMock(ClientInterface::class),
      new RequestStack(),
      $config_factory,
      $this->createMock(ArticleManager::class),
    );
    if ($remote_cache) {
      $property = new \ReflectionProperty($remote_source, 'remoteDataCache');
      $property->setValue($remote_source, $remote_cache);
    }
    return $remote_source;
  }

  /**
   * Mock an HTTP client that fails the test if the remote is called.
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
        Assert::fail('HPC Content Module HTTP post must not be called on a remote data cache hit.');
        throw new \LogicException('Unreachable.');
      }

    };
  }

  /**
   * Create a remote data cache item.
   *
   * @param \Drupal\ghi_content\RemoteResponse\RemoteResponse $payload
   *   The cached remote response.
   *
   * @return \Drupal\hpc_remote_data_cache\RemoteDataCacheItem
   *   The remote data cache item.
   */
  private function createRemoteDataCacheItem(RemoteResponse $payload): RemoteDataCacheItem {
    return new RemoteDataCacheItem(
      'hpc_content_module_graphql:test',
      'hpc_content_module_graphql',
      'https://content.example.org/ncms',
      '{"query":"query { article(id:123) { id }}"}',
      ['remote_source_id' => 'hpc_content_module'],
      $payload,
      100,
      100,
      100,
      1200,
      1600,
      FALSE,
      0,
      0,
      100,
      0,
      NULL,
      100,
      1000,
      ['hpc_content_module:article:123'],
    );
  }

}
