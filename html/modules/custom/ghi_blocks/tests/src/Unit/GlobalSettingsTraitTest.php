<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\ghi_blocks\Traits\GlobalSettingsTrait;
use Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan;
use Drupal\ghi_sections\SectionManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests global settings access and plan type icons.
 */
#[Group('ghi_blocks')]
class GlobalSettingsTraitTest extends UnitTestCase {

  /**
   * Tests reading settings for configured and missing years.
   */
  public function testYearConfig(): void {
    $settings = ['plan_type_icons' => TRUE];
    $container = new ContainerBuilder();
    $container->set('config.factory', $this->getConfigFactoryStub([
      'ghi_blocks.global_settings' => ['2026' => $settings],
    ]));
    \Drupal::setContainer($container);

    $consumer = new class() {
      use GlobalSettingsTrait;
    };
    $this->assertSame($settings, $consumer->getYearConfig('2026'));
    $this->assertNull($consumer->getYearConfig('2025'));
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
    $consumer = new class() {
      use GlobalSettingsTrait;
    };
    $method = new \ReflectionMethod($consumer, 'applyGlobalConfigurationTable');
    $method->invokeArgs($consumer, [&$header, &$rows, &$cache_tags, '2026', [1 => $plan]]);

    $this->assertArrayNotHasKey('type', $header);
    $this->assertArrayNotHasKey('type', $rows[1]);
    $tooltip = $rows[1]['name']['data']['tooltips']['#tooltips'][0];
    $this->assertSame('HRP', $tooltip['#tag_content']);
    $this->assertSame('Humanitarian response plan', $tooltip['#tooltip']);
  }

}
