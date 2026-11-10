<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\Core\Form\FormState;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\ConfigValidationInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanHeadlineFigures;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\PlanMetricResolver;
use Drupal\ghi_plans\Plugin\EndpointQuery\FlowSearchQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\GoverningEntityQuery;
use Drupal\ghi_plans\ValueObject\PlanMetricValues;
use Drupal\hpc_api\Query\EndpointQueryManager;
use Drupal\hpc_api\Query\FabricQueryManager;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;

/**
 * Tests the plan headline figures block plugin.
 */
#[Group('ghi_blocks')]
class PlanHeadlineFiguresTest extends PlanBlockKernelTestBase {

  /**
   * Tests the block properties.
   */
  public function testBlockProperties() {
    $plugin = $this->getBlockPlugin(FALSE);
    $this->assertInstanceOf(PlanHeadlineFigures::class, $plugin);
    $this->assertInstanceOf(MultiStepFormBlockInterface::class, $plugin);
    $this->assertInstanceOf(ConfigurableTableBlockInterface::class, $plugin);
    $this->assertInstanceOf(ConfigValidationInterface::class, $plugin);

    $allowed_item_types = $plugin->getAllowedItemTypes();
    $this->assertCount(8, $allowed_item_types);
    $this->assertArrayHasKey('item_group', $allowed_item_types);
    $this->assertArrayHasKey('line_break', $allowed_item_types);
    $this->assertArrayHasKey('funding_data', $allowed_item_types);
    $this->assertArrayHasKey('entity_counter', $allowed_item_types);
    $this->assertArrayHasKey('project_counter', $allowed_item_types);
    $this->assertArrayHasKey('attachment_data', $allowed_item_types);
    $this->assertArrayHasKey('plan_metric_data', $allowed_item_types);
    $this->assertArrayHasKey('label_value', $allowed_item_types);

    $this->assertNull($plugin->label());

    $config_forms = $plugin->metadata()->configForms;
    $this->assertIsArray($config_forms);
    $this->assertCount(2, $config_forms);
    $this->assertArrayHasKey($plugin->getDefaultSubform(), $config_forms);
    $this->assertEquals('key_figures', $plugin->getDefaultSubform());
  }

  /**
   * Tests the buildContent method.
   */
  public function testBuildContent() {
    $plugin = $this->getBlockPlugin(FALSE);
    $build = $plugin->buildContent();
    $this->assertNull($build);

    $plugin = $this->getBlockPlugin();
    $build = $plugin->buildContent();
    $this->assertIsArray($build);
    $this->assertArrayHasKey(0, $build);
    $this->assertEquals('tab_container', $build[0]['#theme']);
    $this->assertEquals('Population', $build[0]['#tabs'][0]['title']['#markup']);
    $this->assertCount(1, $build[0]['#tabs'][0]['items']['#items']);
    $item = $build[0]['#tabs'][0]['items']['#items'][0];
    $this->assertEquals('label_value', $item['#wrapper_attributes']['data-item-type']);
    $this->assertEquals(100, $item['#wrapper_attributes']['data-raw-value']);
  }

  /**
   * Tests source-aware plan metrics in headline figures.
   */
  public function testPlanMetricData(): void {
    $this->setPlanMetricValues(['in_need' => 125000]);

    $plugin = $this->getBlockPlugin([
      'key_figures' => [
        'items' => [
          [
            'id' => 0,
            'item_type' => 'item_group',
            'config' => ['label' => 'Population'],
          ],
          [
            'id' => 1,
            'item_type' => 'plan_metric_data',
            'config' => ['metric' => 'in_need'],
            'pid' => 0,
          ],
        ],
      ],
    ]);
    $build = $plugin->buildContent();
    $item = $build[0]['#tabs'][0]['items']['#items'][0];
    $this->assertEquals('People in need', $item['#title']);
    $this->assertEquals(125000, $item[0]['#amount']);
    $this->assertEquals('gho', $item['#wrapper_attributes']['data-plan-data-source']);
    $this->assertContains('plan_id:' . $plugin->getContextValue('plan')->getSourceId(), $item[0]['#cache']['tags']);
  }

  /**
   * Tests calculations and formatting for source-aware plan metrics.
   */
  public function testCalculatedPlanMetricData(): void {
    $this->setPlanMetricValues([
      'in_need' => 100,
      'target' => 80,
      'expected_reach' => 0,
      'requirements' => 1000,
      'funding' => 400,
    ]);

    $plugin = $this->getBlockPlugin([
      'key_figures' => [
        'items' => [
          [
            'id' => 0,
            'item_type' => 'item_group',
            'config' => ['label' => 'Population'],
          ],
          [
            'id' => 1,
            'item_type' => 'plan_metric_data',
            'config' => [
              'metric' => 'target',
              'processing' => 'calculated',
              'metric_2' => 'in_need',
              'calculation' => 'percentage',
              'formatting' => 'auto',
            ],
            'pid' => 0,
          ],
        ],
      ],
    ]);

    $build = $plugin->buildContent();
    $item = $build[0]['#tabs'][0]['items']['#items'][0];
    $this->assertSame(0.8, $item['#wrapper_attributes']['data-raw-value']);
    $this->assertSame('in_need', $item['#wrapper_attributes']['data-secondary-metric-type']);
    $this->assertSame('percentage', $item['#wrapper_attributes']['data-calculation']);
    $this->assertSame('hpc_percent', $item[0]['#theme']);
    $this->assertSame(80.0, $item[0]['#percent']);

    $calculations = [
      'addition' => 180,
      'substraction' => -20,
      'division' => 0.8,
      'percentage' => 0.8,
    ];
    foreach ($calculations as $calculation => $expected) {
      $item_type = $plugin->getItemTypePluginForColumn([
        'item_type' => 'plan_metric_data',
        'config' => [
          'metric' => 'target',
          'processing' => 'calculated',
          'metric_2' => 'in_need',
          'calculation' => $calculation,
        ],
      ]);
      $this->assertSame($expected, $item_type->getValue());
    }

    $division_by_zero = $plugin->getItemTypePluginForColumn([
      'item_type' => 'plan_metric_data',
      'config' => [
        'metric' => 'in_need',
        'processing' => 'calculated',
        'metric_2' => 'expected_reach',
        'calculation' => 'division',
      ],
    ]);
    $this->assertNull($division_by_zero->getValue());

    $missing_operand = $plugin->getItemTypePluginForColumn([
      'item_type' => 'plan_metric_data',
      'config' => [
        'metric' => 'target',
        'processing' => 'calculated',
        'metric_2' => 'latest_reach',
        'calculation' => 'addition',
      ],
    ]);
    $this->assertNull($missing_operand->getValue());

    $invalid_calculation = $plugin->getItemTypePluginForColumn([
      'item_type' => 'plan_metric_data',
      'config' => [
        'metric' => 'target',
        'processing' => 'calculated',
      ],
    ]);
    $errors = $invalid_calculation->getConfigurationErrors();
    $this->assertContains('No second plan-level metric configured', $errors);
    $this->assertContains('No calculation configured', $errors);

    $coverage = $plugin->getItemTypePluginForColumn([
      'item_type' => 'plan_metric_data',
      'config' => ['metric' => 'coverage'],
    ]);
    $this->assertSame(0.4, $coverage->getValue());
    $this->assertSame(40.0, $coverage->getRenderArray()['#percent']);

    $funding_gap = $plugin->getItemTypePluginForColumn([
      'item_type' => 'plan_metric_data',
      'config' => ['metric' => 'funding_gap'],
    ]);
    $this->assertSame(600, $funding_gap->getValue());
    $this->assertSame('hpc_currency', $funding_gap->getRenderArray()['#theme']);
    $this->assertNull($funding_gap->getRenderArray()['#decimals']);

    $financial_metric = $plugin->getItemTypePluginForColumn([
      'item_type' => 'plan_metric_data',
      'config' => ['metric' => 'original_requirements'],
    ]);
    $form = $financial_metric->buildForm([
      '#parents' => ['config'],
      '#array_parents' => ['config'],
    ], new FormState());
    $this->assertSame(['Population data', 'Financial data'], array_keys($form['metric']['#options']));
    $this->assertSame('Original requirements ($)', (string) $form['metric']['#options']['Financial data']['original_requirements']);
    $this->assertSame('Funding gap ($)', (string) $form['metric']['#options']['Financial data']['funding_gap']);
    $this->assertSame('Funding coverage (%)', (string) $form['metric']['#options']['Financial data']['coverage']);
    $this->assertStringContainsString("Available values depend on the plan's configured metric source. If the selected metric is not provided by that source, the item remains empty.", (string) $form['metric']['#title']);
    $this->assertStringContainsString('plan-metric-form-label', (string) $form['metric']['#title']);
    $this->assertStringContainsString('material-symbol-help', (string) $form['metric']['#title']);
    $this->assertStringContainsString('Current plan metric source', (string) $form['metric_source']['#title']);
    $this->assertStringContainsString('This is a plan-wide setting. Change it on the Plan metrics tab of the plan base object.', (string) $form['metric_source']['#title']);
    $this->assertStringContainsString('material-symbol-help', (string) $form['metric_source']['#title']);
    $this->assertSame('Country Module data', (string) $form['metric_source']['#markup']);
    $this->assertSame('No value is currently available for this metric from Country Module data.', (string) $form['value_preview']['#description']);
    $this->assertSame('Original ($)', (string) $financial_metric->getDefaultLabel());
    $this->assertSame('Unmet ($)', (string) $financial_metric->getDefaultLabel('funding_gap'));
    $this->assertSame('% Funded', (string) $financial_metric->getDefaultLabel('coverage'));

  }

  /**
   * Tests percent headline figures trim insignificant decimals.
   */
  public function testPercentHeadlineFiguresTrimInsignificantDecimals() {
    $flow_search_query = $this->prophesize(FlowSearchQuery::class);
    $flow_search_query->setPlaceholder(Argument::cetera())->willReturn(NULL);

    $endpoint_query_manager = $this->prophesize(EndpointQueryManager::class);
    $endpoint_query_manager->createInstance('flow_search_query')->willReturn($flow_search_query->reveal());

    $fabric_query_manager = $this->prophesize(FabricQueryManager::class);
    $fabric_query_manager->hasDefinition(Argument::any())->willReturn(FALSE);
    $fabric_query_manager->createInstance('governing_entity')->willReturn($this->prophesize(GoverningEntityQuery::class)->reveal());
    $fabric_query_manager->createInstance('attachment')->willReturn($this->prophesize(AttachmentQuery::class)->reveal());

    $container = \Drupal::getContainer();
    $container->set('plugin.manager.endpoint_query_manager', $endpoint_query_manager->reveal());
    $container->set('plugin.manager.fabric_query_manager', $fabric_query_manager->reveal());
    \Drupal::setContainer($container);

    $plugin = $this->getBlockPlugin([
      'key_figures' => [
        'items' => [
          [
            'id' => 0,
            'item_type' => 'item_group',
            'config' => [
              'label' => 'Funding',
            ],
          ],
          [
            'id' => 1,
            'item_type' => 'funding_data',
            'config' => [
              'data_type' => 'funding_coverage',
            ],
            'pid' => 0,
          ],
          [
            'id' => 2,
            'item_type' => 'label_value',
            'config' => [
              'label' => 'Reached',
              'value' => 0.3,
              'formatting' => 'percent',
            ],
            'pid' => 0,
          ],
        ],
      ],
    ]);

    $build = $plugin->buildContent();
    $items = $build[0]['#tabs'][0]['items']['#items'];

    $this->assertEquals('hpc_percent', $items[0][0]['content']['#theme']);
    $this->assertTrue($items[0][0]['content']['#compact_precision']);
    $this->assertEquals('hpc_percent', $items[1][0]['#theme']);
    $this->assertTrue($items[1][0]['#compact_precision']);
  }

  /**
   * Tests that missing headline funding renders as zero.
   */
  public function testMissingHeadlineFundingRendersZero(): void {
    $flow_search_query = $this->prophesize(FlowSearchQuery::class);
    $flow_search_query->setPlaceholder(Argument::cetera())->willReturn(NULL);

    $endpoint_query_manager = $this->prophesize(EndpointQueryManager::class);
    $endpoint_query_manager->createInstance('flow_search_query')->willReturn($flow_search_query->reveal());

    $fabric_query_manager = $this->prophesize(FabricQueryManager::class);
    $fabric_query_manager->hasDefinition(Argument::any())->willReturn(FALSE);
    $fabric_query_manager->createInstance('governing_entity')->willReturn($this->prophesize(GoverningEntityQuery::class)->reveal());
    $fabric_query_manager->createInstance('attachment')->willReturn($this->prophesize(AttachmentQuery::class)->reveal());

    $container = \Drupal::getContainer();
    $container->set('plugin.manager.endpoint_query_manager', $endpoint_query_manager->reveal());
    $container->set('plugin.manager.fabric_query_manager', $fabric_query_manager->reveal());
    \Drupal::setContainer($container);

    $plugin = $this->getBlockPlugin([
      'key_figures' => [
        'items' => [
          [
            'id' => 0,
            'item_type' => 'item_group',
            'config' => [
              'label' => 'Funding',
            ],
          ],
          [
            'id' => 1,
            'item_type' => 'funding_data',
            'config' => [
              'data_type' => 'funding_totals',
            ],
            'pid' => 0,
          ],
        ],
      ],
    ]);

    $build = $plugin->buildContent();
    $item = $build[0]['#tabs'][0]['items']['#items'][0];

    $this->assertSame(0, $item['#wrapper_attributes']['data-raw-value']);
    $this->assertNotContains('not-available', $item['#wrapper_attributes']['class']);
    $this->assertSame(0, $item[0]['content']['#value']);
  }

  /**
   * Tests the block forms.
   */
  public function testBlockForms() {
    $plugin = $this->getBlockPlugin();

    $form_state = new FormState();
    $form_state->set('block', $plugin);

    // Test the key figures form.
    $key_figures_form = $plugin->keyFiguresForm([], $form_state);
    $this->assertArrayHasKey('items', $key_figures_form);

    // Test the display form.
    $display_form = $plugin->displayForm([], $form_state);
    $this->assertArrayHasKey('comment', $display_form);
  }

  /**
   * Tests the config validation and fixing method.
   */
  public function testConfigValidation() {
    $plugin = $this->getBlockPlugin(FALSE);
    $errors = $plugin->getConfigErrors();
    $this->assertIsArray($errors);
    $this->assertCount(1, $errors);
    $this->assertEquals('No configured items', $errors[0]);
    $conf = $plugin->getBlockConfig();
    $plugin->fixConfigErrors();
    $this->assertEquals($conf, $plugin->getBlockConfig());

    $plugin = $this->getBlockPlugin();
    $errors = $plugin->getConfigErrors();
    $this->assertIsArray($errors);
    $this->assertEmpty($errors);
    $conf = $plugin->getBlockConfig();
    $plugin->fixConfigErrors();
    $this->assertEquals($conf, $plugin->getBlockConfig());
  }

  /**
   * Get a block plugin.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Plan\PlanHeadlineFigures
   *   The block plugin.
   */
  private function getBlockPlugin($configuration = []) {
    if ($configuration === FALSE) {
      $configuration = [];
    }
    elseif (empty($configuration)) {
      $configuration = [
        'key_figures' => [
          'items' => [
            [
              'id' => 0,
              'item_type' => 'item_group',
              'config' => [
                'label' => 'Population',
              ],
            ],
            [
              'id' => 1,
              'item_type' => 'label_value',
              'config' => [
                'label' => 'Label',
                'value' => 100,
              ],
              'pid' => 0,
            ],
          ],
        ],
      ];
    }
    $contexts = $this->getPlanSectionContexts();
    return $this->createBlockPlugin('plan_headline_figures', $configuration ?: [], $contexts);
  }

  /**
   * Installs a predictable plan metric resolver for the current test.
   */
  private function setPlanMetricValues(array $values): void {
    $resolver = $this->createMock(PlanMetricResolver::class);
    $resolver->method('resolve')->willReturnCallback(function (array $plans) use ($values): array {
      $plan_id = array_key_first($plans);
      return [$plan_id => new PlanMetricValues(Plan::DATA_SOURCE_GHO, $values, cacheTags: ['plan_id:' . $plan_id])];
    });
    $container = \Drupal::getContainer();
    $container->set('ghi_plans.plan_metric_resolver', $resolver);
    \Drupal::setContainer($container);
  }

}
