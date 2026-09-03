<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Global;

use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\ghi_blocks\Plugin\Block\Generic\ExternalWidget;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;
use Drupal\Tests\ghi_blocks\Kernel\BlockKernelTestBase;

/**
 * Tests the external widget block plugin.
 *
 * @group ghi_blocks
 */
class ExternalWidgetBlockTest extends BlockKernelTestBase {

  use BaseObjectTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'field',
    'node',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('base_object');
    $this->createBaseObjectType([
      'id' => 'plan',
    ]);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
  }

  /**
   * Tests the block properties.
   */
  public function testBlockProperties() {
    $widget = $this->buildWidgetConfiguration();
    $plugin = $this->getBlockPlugin([$widget]);
    $this->assertInstanceOf(ExternalWidget::class, $plugin);

    $allowed_hosts = $this->callPrivateMethod($plugin, 'getAllowedHosts');
    $this->assertCount(4, $allowed_hosts);
    $this->assertArrayHasKey('humdata.org', $allowed_hosts);
    $this->assertArrayHasKey('powerbi.com', $allowed_hosts);
    $this->assertArrayHasKey('tableau.com', $allowed_hosts);
    $this->assertArrayHasKey('experience.arcgis.com', $allowed_hosts);
  }

  /**
   * Tests the block build.
   */
  public function testBlockBuild() {
    $widgets = [
      $this->buildWidgetConfiguration('https://app.powerbi.com/view'),
    ];
    $plugin = $this->getBlockPlugin($widgets);

    $build = $plugin->buildContent();
    $this->assertArrayHasKey(0, $build);
    $this->assertArrayNotHasKey(1, $build);

    // Add an additional empty widget.
    $widgets = [
      $this->buildWidgetConfiguration('https://app.powerbi.com/view'),
      $this->buildWidgetConfiguration(),
    ];
    $plugin = $this->getBlockPlugin($widgets);

    $build = $plugin->buildContent();
    $this->assertArrayHasKey(0, $build);
    $this->assertArrayNotHasKey(1, $build);
  }

  /**
   * Tests the block build with no widgets.
   */
  public function testBlockBuildNoWidgets() {
    $widget = $this->buildWidgetConfiguration();
    $plugin = $this->getBlockPlugin([$widget]);

    $build = $plugin->buildContent();
    $this->assertNull($build);
  }

  /**
   * Tests HDX URL processing with populated and empty year contexts.
   *
   * @dataProvider yearContextProvider
   */
  public function testBuildHdxWidgetWithYearContext(?int $year, bool $article_owner) {
    $data_url = 'https://docs.google.com/spreadsheets/d/' . ExternalWidget::GOOGLE_SHEET . '/export?format=csv';
    $proxy_url = 'https://proxy.hxlstandard.org/data.csv?filter01=select&select-query01-01=%23country%2Bcode=G&filter02=select&select-query02-01=%23date%2Byear=2020&url=' . rawurlencode($data_url);
    $chart = [
      'bites' => [
        ['uiProperties' => ['dataTitle' => 'Response plan funding', 'title' => 'Configured chart title']],
      ],
    ];
    $widget_url = 'https://data.humdata.org/visualization/quickcharts.html#;url=' . rawurlencode($proxy_url) . ';embeddedConfig=' . rawurlencode(json_encode($chart));
    $plugin = $this->getBlockPlugin([$this->buildWidgetConfiguration($widget_url)]);
    $plugin->setContextMapping(['year' => 'year']);
    $plugin->setContext('year', new Context(new ContextDefinition('integer', required: FALSE), $year));
    if ($article_owner) {
      $plugin->setContext('layout_builder.entity', EntityContext::fromEntity(Node::create(['type' => 'article'])));
    }

    $build = $plugin->buildContent();
    $this->assertSame('iframe', $build[0][0]['#tag']);
    $parts = explode(';', $build[0][0]['#attributes']['src']);
    array_shift($parts);
    $params = [];
    foreach ($parts as $part) {
      [$key, $value] = explode('=', $part, 2);
      $params[$key] = rawurldecode($value);
    }

    if ($year === NULL || $article_owner) {
      // Without a page year, keep the chart's configured filters and title.
      $this->assertSame($proxy_url, $params['url']);
      $this->assertSame($chart, json_decode($params['embeddedConfig'], TRUE));
    }
    else {
      parse_str(parse_url($params['url'], PHP_URL_QUERY), $query);
      $this->assertSame('#date+year > {{ ' . $year . ' - 10 }}', $query['select-query02-01']);
      $this->assertSame('#date+year <= ' . $year, $query['select-query03-01']);
      $processed_chart = json_decode($params['embeddedConfig'], TRUE);
      $this->assertStringContainsString((string) $year, $processed_chart['bites'][0]['uiProperties']['title']);
    }
  }

  /**
   * Provides populated and empty contexts, including an empty required context.
   */
  public function yearContextProvider(): array {
    return [
      'populated year' => [2025, FALSE],
      'article with no year' => [2025, TRUE],
      'empty optional year' => [NULL, FALSE],
    ];
  }

  /**
   * Tests the block forms.
   */
  public function testBlockForms() {
    $plugin = $this->getBlockPlugin();

    $form_state = new FormState();
    $form_state->set('block', $plugin);
    $form = $plugin->getConfigForm(['#parents' => []], $form_state);
    $this->assertArrayHasKey('select_number', $form);
    $this->assertArrayHasKey('widgets', $form);

    // Prepare form validation.
    $form['widgets'][1]['widget_url']['#parents'] = ['container', 'widgets', 1];
    $form_state->set('current_subform', 'basic');

    // Validate a valid embed code.
    $form_state->setValue(['basic', 'select_number'], 1);
    $form_state->setValue(['basic', 'widgets', 1], $this->buildWidgetConfiguration());
    $plugin->blockValidate(['container' => $form], $form_state);
    $this->assertNotEmpty($form_state->getErrors());

    $form_state->clearErrors();
    $form_state->setValue(['basic', 'widgets', 1], $this->buildWidgetConfiguration('https://app.powerbi.com/view'));
    $plugin->blockValidate(['container' => $form], $form_state);
    $this->assertEmpty($form_state->getErrors());

    $form_state->clearErrors();
    $form_state->setValue(['basic', 'widgets', 1], $this->buildWidgetConfiguration('https://invalid.url/view'));
    $plugin->blockValidate(['container' => $form], $form_state);
    $this->assertNotEmpty($form_state->getErrors());
  }

  /**
   * Get a block plugin.
   *
   * @param array $widgets
   *   The widget configurations to add to the plugin.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Generic\ExternalWidget
   *   The block plugin.
   */
  private function getBlockPlugin($widgets = []) {
    $configuration = [
      'select_number' => min(count($widgets), 2),
      'widgets' => $widgets,
    ];
    return $this->createBlockPlugin('generic_external_widget', $configuration);
  }

  /**
   * Build a widget configuration.
   *
   * @param string $url
   *   The widget url.
   * @param bool $process
   *   Whether to process the url.
   * @param bool $skip_validation
   *   Whether to skipt the validation.
   * @param string $height
   *   The height of the widget.
   *
   * @return array
   *   The configuration array for a widget.
   */
  private function buildWidgetConfiguration($url = '', $process = TRUE, $skip_validation = FALSE, $height = '600px') {
    return [
      'widget_url' => $url,
      'process_widget_url' => $process,
      'widget_url_skip_validation' => $skip_validation,
      'widget_height' => $height,
    ];
  }

}
