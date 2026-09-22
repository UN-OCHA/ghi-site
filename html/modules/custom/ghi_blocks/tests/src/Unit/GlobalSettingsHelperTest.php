<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\ghi_blocks\Helpers\GlobalSettingsHelper;
use Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan;
use Drupal\ghi_sections\SectionManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests global settings access and plan type icons.
 */
#[Group('ghi_blocks')]
class GlobalSettingsHelperTest extends UnitTestCase {

  /**
   * Tests that the trait and static helper read the same year settings.
   */
  public function testYearConfig(): void {
    $settings = ['plan_type_icons' => TRUE];
    $container = new ContainerBuilder();
    $container->set('config.factory', $this->getConfigFactoryStub([
      'ghi_blocks.global_settings' => ['2026' => $settings],
    ]));
    \Drupal::setContainer($container);

    $helper = new GlobalSettingsHelper();
    $this->assertSame($settings, $helper->getYearConfig('2026'));
    $this->assertSame($settings, GlobalSettingsHelper::getConfig('2026'));
    $this->assertNull($helper->getYearConfig('2025'));
  }

  /**
   * Tests that plan type icons do not capture an undefined local variable.
   */
  public function testPlanTypeIcons(): void {
    $container = new ContainerBuilder();
    $container->set('config.factory', $this->getConfigFactoryStub([
      'ghi_blocks.global_settings' => ['2026' => ['plan_type_icons' => TRUE]],
    ]));
    $container->set('ghi_sections.manager', $this->createMock(SectionManager::class));
    \Drupal::setContainer($container);

    $plan = $this->createMock(PlanOverviewPlan::class);
    $plan->method('getTypeName')->with(TRUE)->willReturn('Humanitarian response plan');
    $plan->method('getTypeShortName')->willReturn('HRP');
    $plan->method('getEntity')->willReturn(NULL);
    $header = ['name' => 'Name', 'type' => 'Type'];
    $rows = [1 => ['name' => 'Example', 'type' => 'HRP']];
    $cache_tags = [];
    $helper = new GlobalSettingsHelper();
    $method = new \ReflectionMethod($helper, 'applyGlobalConfigurationTable');
    $method->invokeArgs($helper, [&$header, &$rows, &$cache_tags, '2026', [1 => $plan]]);

    $this->assertArrayNotHasKey('type', $header);
    $this->assertArrayNotHasKey('type', $rows[1]);
    $tooltip = $rows[1]['name']['data']['tooltips']['#tooltips'][0];
    $this->assertSame('HRP', $tooltip['#tag_content']);
    $this->assertSame('Humanitarian response plan', $tooltip['#tooltip']);
  }

}
