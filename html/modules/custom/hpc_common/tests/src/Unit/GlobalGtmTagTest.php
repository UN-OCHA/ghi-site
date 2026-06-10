<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\hpc_common\GlobalGtmTag;

/**
 * Tests the additional global GTM tag service.
 *
 * @coversDefaultClass \Drupal\hpc_common\GlobalGtmTag
 */
class GlobalGtmTagTest extends UnitTestCase {

  /**
   * Tests attaching the configured GTM container.
   *
   * @covers ::attachPageHead
   * @covers ::attachPageTop
   */
  public function testAttachesConfiguredContainer(): void {
    $global_gtm_tag = $this->createGlobalGtmTag(' GTM-SECOND<script> ');
    $page = [];
    $page_top = [];

    $global_gtm_tag->attachPageHead($page);
    $global_gtm_tag->attachPageTop($page_top);

    $this->assertArrayHasKey('#attached', $page);
    $this->assertCount(1, $page['#attached']['html_head']);
    $this->assertSame('hpc_common_global_gtm_tag', $page['#attached']['html_head'][0][1]);
    $this->assertSame('script', $page['#attached']['html_head'][0][0]['#tag']);
    $this->assertStringContainsString("'GTM-SECONDscript'", $page['#attached']['html_head'][0][0]['#value']);
    $this->assertStringContainsString('googletagmanager.com/gtm.js', $page['#attached']['html_head'][0][0]['#value']);

    $this->assertArrayHasKey('hpc_common_global_gtm_tag', $page_top);
    $this->assertTrue($page_top['hpc_common_global_gtm_tag']['#noscript']);
    $this->assertSame('iframe', $page_top['hpc_common_global_gtm_tag']['#tag']);
    $this->assertSame('https://www.googletagmanager.com/ns.html?id=GTM-SECONDscript', $page_top['hpc_common_global_gtm_tag']['#attributes']['src']);
  }

  /**
   * Tests that the service does not attach without a configured container.
   *
   * @covers ::attachPageHead
   * @covers ::attachPageTop
   */
  public function testDoesNotAttachWithoutConfiguredContainer(): void {
    $global_gtm_tag = $this->createGlobalGtmTag('');
    $page = [];
    $page_top = [];

    $global_gtm_tag->attachPageHead($page);
    $global_gtm_tag->attachPageTop($page_top);

    $this->assertSame([], $page);
    $this->assertSame([], $page_top);
  }

  /**
   * Tests that the service follows the primary GTM enable setting.
   *
   * @covers ::attachPageHead
   * @covers ::attachPageTop
   */
  public function testDoesNotAttachWhenGtmIsDisabled(): void {
    $global_gtm_tag = $this->createGlobalGtmTag('GTM-SECOND', ['enable' => FALSE]);
    $page = [];
    $page_top = [];

    $global_gtm_tag->attachPageHead($page);
    $global_gtm_tag->attachPageTop($page_top);

    $this->assertSame([], $page);
    $this->assertSame([], $page_top);
  }

  /**
   * Tests that the service follows the primary GTM admin page setting.
   *
   * @covers ::attachPageHead
   * @covers ::attachPageTop
   */
  public function testDoesNotAttachOnAdminRoutesWhenAdminPagesAreDisabled(): void {
    $global_gtm_tag = $this->createGlobalGtmTag('GTM-SECOND', ['admin-pages' => FALSE], 2, TRUE);
    $page = [];
    $page_top = [];

    $global_gtm_tag->attachPageHead($page);
    $global_gtm_tag->attachPageTop($page_top);

    $this->assertSame([], $page);
    $this->assertSame([], $page_top);
  }

  /**
   * Tests that the service follows the primary GTM admin user setting.
   *
   * @covers ::attachPageHead
   * @covers ::attachPageTop
   */
  public function testDoesNotAttachForAdminUserWhenDisabledForAdmin(): void {
    $global_gtm_tag = $this->createGlobalGtmTag('GTM-SECOND', ['admin-disable' => TRUE], 1);
    $page = [];
    $page_top = [];

    $global_gtm_tag->attachPageHead($page);
    $global_gtm_tag->attachPageTop($page_top);

    $this->assertSame([], $page);
    $this->assertSame([], $page_top);
  }

  /**
   * Creates the service under test.
   *
   * @param string $additional_google_tag
   *   The additional GTM container ID.
   * @param array $gtm_settings
   *   Primary GTM settings overrides.
   * @param int $uid
   *   The current user ID.
   * @param bool $is_admin_route
   *   Whether the current route is an admin route.
   *
   * @return \Drupal\hpc_common\GlobalGtmTag
   *   The service under test.
   */
  protected function createGlobalGtmTag(string $additional_google_tag, array $gtm_settings = [], int $uid = 2, bool $is_admin_route = FALSE): GlobalGtmTag {
    $gtm_settings += [
      'enable' => TRUE,
      'admin-pages' => FALSE,
      'admin-disable' => FALSE,
    ];

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
      ->willReturnCallback(function (string $name) use ($additional_google_tag, $gtm_settings): ImmutableConfig {
        if ($name === 'hpc_common.settings') {
          return $this->createConfig(['additional_google_tag' => $additional_google_tag]);
        }
        if ($name === 'gtm.settings') {
          return $this->createConfig($gtm_settings);
        }
        throw new \InvalidArgumentException(sprintf('Unexpected config requested: %s', $name));
      });

    $current_user = $this->createMock(AccountProxyInterface::class);
    $current_user->method('id')->willReturn($uid);

    $admin_context = $this->createMock(AdminContext::class);
    $admin_context->method('isAdminRoute')->willReturn($is_admin_route);

    return new GlobalGtmTag($config_factory, $current_user, $admin_context);
  }

  /**
   * Creates a config mock.
   *
   * @param array $values
   *   Config values keyed by config property.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The config mock.
   */
  protected function createConfig(array $values): ImmutableConfig {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->willReturnCallback(fn(string $key) => $values[$key] ?? NULL);
    return $config;
  }

}
