<?php

namespace Drupal\Tests\ghi_content\Unit\RemoteSource;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\Plugin\RemoteSource\HpcContentModule;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the HPC content module remote source.
 *
 * @group ghi_content
 */
class HpcContentModuleTest extends UnitTestCase {

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
   *
   * @return \Drupal\ghi_content\Plugin\RemoteSource\HpcContentModule
   *   The remote source plugin.
   */
  private function createRemoteSource(array $runtime_refresh_settings = [], array $overridden_refresh_settings = []): HpcContentModule {
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

    return new HpcContentModule(
      [],
      'hpc_content_module',
      [
        'id' => 'hpc_content_module',
        'label' => 'HPC Content Module',
      ],
      $this->createMock(ClientInterface::class),
      new RequestStack(),
      $config_factory,
      $this->createMock(ArticleManager::class),
    );
  }

}
