<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Global;

use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Render\Markup;
use Drupal\ghi_blocks\Plugin\Block\GlobalPage\PlanOverviewMap;
use Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan;
use Drupal\ghi_plans\Entity\PlanType;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the Plan Overview Map block plugin.
 *
 * @group ghi_blocks
 */
class PlanOverviewMapTest extends PlanBlockKernelTestBase {

  use ProphecyTrait;

  /**
   * Tests the block plugin instantiation.
   */
  public function testBlockPluginInstantiation() {
    $plugin = $this->getBlockPlugin();
    $this->assertInstanceOf(PlanOverviewMap::class, $plugin);
  }

  /**
   * Tests block plugin annotation and metadata.
   */
  public function testBlockPluginAnnotation() {
    $plugin = $this->getBlockPlugin();
    $definition = $plugin->getPluginDefinition();

    $this->assertEquals('global_plan_overview_map', $definition['id']);
    $this->assertEquals('Plan overview map', (string) $definition['admin_label']);
    $this->assertEquals('Global', (string) $definition['category']);
    $this->assertArrayHasKey('data_sources', $definition);
    $this->assertArrayHasKey('plans', $definition['data_sources']);
    $this->assertArrayHasKey('locations', $definition['data_sources']);
    $this->assertArrayHasKey('countries', $definition['data_sources']);
  }

  /**
   * Tests the default block configuration.
   */
  public function testDefaultConfiguration() {
    $plugin = $this->getBlockPlugin();
    $default_config = $this->callPrivateMethod($plugin, 'getConfigurationDefaults');

    $this->assertArrayHasKey('style', $default_config);
    $this->assertEquals('circle', $default_config['style']);
    $this->assertArrayHasKey('search_enabled', $default_config);
    $this->assertFalse($default_config['search_enabled']);
    $this->assertArrayHasKey('disclaimer', $default_config);
    $this->assertNull($default_config['disclaimer']);
  }

  /**
   * Tests the block build with some plans.
   */
  public function testBlockBuildWithPlans() {
    $plugin = $this->getBlockPlugin();

    $plans = [
      $this->mockPlan()->reveal(),
      $this->mockPlan()->reveal(),
    ];
    $this->mockPlanOverviewQuery($plugin, $plans, 2024);

    $build = $plugin->buildContent();

    $this->assertIsArray($build);
    $this->assertEquals('plan_overview_map', $build['#theme']);
    $this->assertArrayHasKey('#chart_id', $build);
    $this->assertNotEmpty($build['#chart_id']);
    $this->assertArrayHasKey('#map_type', $build);
    $this->assertArrayHasKey('#map_tabs', $build);
    $this->assertArrayHasKey('#cache', $build);
    $this->assertNotEmpty($build['#attached']['drupalSettings']['plan_overview_map'][$build['#chart_id']]['json']);
    $data = $build['#attached']['drupalSettings']['plan_overview_map'][$build['#chart_id']]['json'];
    $tabs = array_keys($data);
    $this->assertCount(count($tabs), $build['#map_tabs']['#items']);
    foreach ($data as $tab_data) {
      $this->assertNotEmpty($tab_data);
      $this->assertArrayHasKey('locations', $tab_data);
      $locations = $tab_data['locations'];
      $this->assertCount(count($plans), $locations);
    }
  }

  /**
   * Tests the block build with empty plans.
   */
  public function testBlockBuildWithEmptyPlans() {
    $plugin = $this->getBlockPlugin();

    // Mock the getPlans method to return empty array.
    $plans_query = $this->prophesize('\Drupal\ghi_plans\Plugin\EndpointQuery\PlanOverviewQuery');
    $plans_query->getPlans()->willReturn([]);
    $plans_query->setPlaceholder('year', '2024')->shouldBeCalled();

    // Set the mocked query handler.
    $reflection = new \ReflectionClass($plugin);
    $property = $reflection->getProperty('queryHandlers');
    $property->setAccessible(TRUE);
    $property->setValue($plugin, ['plans' => $plans_query->reveal()]);

    $build = $plugin->buildContent();

    $this->assertIsArray($build);
    $this->assertEquals('plan_overview_map', $build['#theme']);
    $this->assertArrayHasKey('#chart_id', $build);
    $this->assertNotEmpty($build['#chart_id']);
    $this->assertArrayHasKey('#map_type', $build);
    $this->assertArrayHasKey('#map_tabs', $build);
    $this->assertArrayHasKey('#cache', $build);
    $this->assertNotEmpty($build['#attached']['drupalSettings']['plan_overview_map'][$build['#chart_id']]['json']);
    $data = $build['#attached']['drupalSettings']['plan_overview_map'][$build['#chart_id']]['json'];
    $tabs = array_keys($data);
    $this->assertCount(count($tabs), $build['#map_tabs']['#items']);
    foreach ($data as $tab_data) {
      $this->assertNotEmpty($tab_data);
      $this->assertArrayHasKey('locations', $tab_data);
      $this->assertEmpty($tab_data['locations']);
    }
  }

  /**
   * Tests the block build with valid configuration.
   */
  public function testBlockBuildWithValidConfiguration() {
    $plugin = $this->getBlockPlugin();
    $build = $plugin->buildContent();

    $this->assertIsArray($build);
    $this->assertEquals('plan_overview_map', $build['#theme']);
    $this->assertArrayHasKey('#chart_id', $build);
    $this->assertArrayHasKey('#map_type', $build);
    $this->assertArrayHasKey('#attached', $build);
    $this->assertArrayHasKey('library', $build['#attached']);
    $this->assertArrayHasKey('drupalSettings', $build['#attached']);
    $this->assertContains('ghi_blocks/map.gl.plan_overview', $build['#attached']['library']);
  }

  /**
   * Tests cache tags generation.
   */
  public function testCacheTags() {
    $plugin = $this->getBlockPlugin();
    $configuration = $plugin->getConfiguration();
    $configuration['uuid'] = 'block_uuid';
    $plugin->setConfiguration($configuration);

    $plans = [
      $this->mockPlan()->reveal(),
      $this->mockPlan()->reveal(),
    ];
    $this->mockPlanOverviewQuery($plugin, $plans, 2024);

    // Test default cache tags.
    $cache_tags = $plugin->getCacheTags();
    $this->assertIsArray($cache_tags);

    // The cache tags should include the block plugin's cache tags.
    $expected_tags = [
      'global_plan_overview_map:block_uuid',
      'plan:1',
      'plan:2',
    ];
    foreach ($expected_tags as $tag) {
      $this->assertContains($tag, $cache_tags);
    }
  }

  /**
   * Tests the buildCountryModal.
   */
  public function testBuildCountryModal() {
    $plugin = $this->getBlockPlugin();

    // Mock the objects.
    $plan = $this->mockPlan();
    $caseload = (object) [
      'total_population' => 100000,
      'in_need' => 100000,
      'target' => 80000,
      'expected_reach' => 60000,
      'expected_reached' => 60000,
      'reached' => 70000,
      'reached_percent' => 150000,
    ];
    $funding = (object) [
      'total_requirements' => 1000000,
      'total_funding' => 500000,
      'funding_progress' => 0.5,
    ];
    $reporting_period = $this->prophesize('\Drupal\ghi_plans\ApiObjects\PlanReportingPeriod');
    $reporting_period->format('Monitoring period #@period_number<br>@date_range')->willReturn('Monitoring period #1<br>01.01. - 31.03.2024');

    $country_modal = $this->callPrivateMethod($plugin, 'buildCountryModal', [
      $plan->reveal(),
      $caseload, $funding,
      $reporting_period->reveal(),
    ]);

    $this->assertInstanceOf(Markup::class, $country_modal);
  }

  /**
   * Tests configuration form structure.
   */
  public function testConfigurationForm() {
    $plugin = $this->getBlockPlugin();
    $form_state = new FormState();

    $form = $plugin->getConfigForm([], $form_state);

    $this->assertIsArray($form);
    // The form structure depends on the specific implementation,
    // but we can test that it returns an array.
  }

  /**
   * Tests legend building with different plan types.
   */
  public function testBuildLegendItems() {
    $plugin = $this->getBlockPlugin();

    $legend = $this->callPrivateMethod($plugin, 'buildLegendItems');

    $this->assertIsArray($legend);
    // Should not contain 'cap' key as it's explicitly removed.
    $this->assertArrayNotHasKey('cap', $legend);
  }

  /**
   * Tests block contexts requirements.
   */
  public function testBlockContexts() {
    $plugin = $this->getBlockPlugin();
    $definition = $plugin->getPluginDefinition();

    $this->assertArrayHasKey('context_definitions', $definition);
    $this->assertArrayHasKey('node', $definition['context_definitions']);
    $this->assertArrayHasKey('year', $definition['context_definitions']);
  }

  /**
   * Get a block plugin with default configuration.
   *
   * @param array $additional_config
   *   Additional configuration to merge with defaults.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\GlobalPage\PlanOverviewMap
   *   The block plugin instance.
   */
  private function getBlockPlugin(array $additional_config = []) {
    $configuration = array_merge([
      'map' => [
        'style' => 'circle',
        'search_enabled' => FALSE,
        'disclaimer' => '',
      ],
    ], $additional_config);

    $contexts = [
      'year' => new Context(new ContextDefinition('string'), '2024'),
    ];

    return $this->createBlockPlugin('global_plan_overview_map', $configuration, $contexts);
  }

  /**
   * Creates a mock plan for testing purposes.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   *   The mocked plan object.
   */
  private function mockPlan() {
    static $id = 0;
    $id++;

    $plan_name = $this->randomString();
    $country_id = $id;

    // Mock country.
    $country = $this->prophesize('\Drupal\ghi_base_objects\ApiObjects\Country');
    $country->id()->willReturn($country_id);
    $country->getName()->willReturn($this->randomString());
    $country->getLatLng()->willReturn([0.0, 0.0]);

    // Mock a plan entity.
    $plan_entity = $this->prophesize('\Drupal\ghi_plans\Entity\Plan');
    $plan_entity->id()->willReturn($id);
    $plan_entity->getShortName()->willReturn($plan_name);
    $plan_entity->getYear()->willReturn(2024);
    $plan_entity->getFocusCountryMapLocation($country->reveal())->willReturn($country->reveal());
    $plan_entity->getCacheTags()->willReturn(['plan:' . $id]);
    $plan_entity->hasField('field_footnotes')->willReturn(FALSE);
    $plan_entity->needsYear()->willReturn(FALSE);

    // Mock plan type.
    $plan_type = $this->prophesize(PlanType::class);
    $plan_type->id()->willReturn('hrp');
    $plan_type->label()->willReturn('Humanitarian Response Plan');
    $plan_type->getAbbreviation()->willReturn('HRP');

    // Mock a plan overview plan object.
    $plan = $this->prophesize(PlanOverviewPlan::class);
    $plan->id()->willReturn($id);
    $plan->getName()->willReturn($plan_name);
    $plan->getEntity()->willReturn($plan_entity->reveal());
    $plan->getFunding()->willReturn(1000000);
    $plan->getRequirements()->willReturn(2000000);
    $plan->getCaseloadValue('inNeed')->willReturn(100000);
    $plan->getCaseloadValue('target')->willReturn(80000);
    $plan->getCaseloadValue('latestReach')->willReturn(60000);
    $plan->getCaseloadValue('expectedReach')->willReturn(70000);
    $plan->getCaseloadValue('totalPopulation')->willReturn(150000);
    $plan->getCoverage()->willReturn(0.5);
    $plan->getPlanType()->willReturn($plan_type->reveal());
    $plan->getTypeName()->willReturn('Humanitarian Response Plan');
    $plan->getTypeShortName()->willReturn('HRP');
    $plan->getTypeOrder()->willReturn(1);
    $plan->isRrp()->willReturn(FALSE);
    $plan->getCountries()->willReturn([$country_id => $country->reveal()]);
    $plan->getCountry()->willReturn($country->reveal());
    $plan->getPlanDocumentUri()->willReturn(NULL);
    $plan->getPlanStatus()->willReturn(FALSE);
    $plan->getPlanStatusLabel()->willReturn(NULL);
    $plan->getLastPublishedReportingPeriod()->willReturn(NULL);
    return $plan;
  }

  /**
   * Mocks the getPlans method of the PlanOverviewMap plugin.
   *
   * @param \Drupal\ghi_blocks\Plugin\Block\GlobalPage\PlanOverviewMap $plugin
   *   The PlanOverviewMap plugin instance.
   * @param array $plans
   *   An array of mocked plans.
   * @param int $year
   *   The year used in the query placeholder.
   */
  private function mockPlanOverviewQuery(PlanOverviewMap $plugin, array $plans, int $year) {
    $plan_ids = array_map(function ($plan) {
      return $plan->id();
    }, $plans);

    // Mock the getPlans method to return empty array.
    $plans_query = $this->prophesize('\Drupal\ghi_plans\Plugin\EndpointQuery\PlanOverviewQuery');
    $plans_query->getPlans()->willReturn(array_combine($plan_ids, $plans));
    $plans_query->setPlaceholder('year', $year)->shouldBeCalled();

    // Set the mocked query handler.
    $reflection = new \ReflectionClass($plugin);
    $property = $reflection->getProperty('queryHandlers');
    $property->setAccessible(TRUE);
    $property->setValue($plugin, ['plans' => $plans_query->reveal()]);
  }

}
