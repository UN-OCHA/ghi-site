<?php

namespace Drupal\Tests\ghi_blocks\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\ghi_blocks\Plugin\Block\Generic\ExternalWidget;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;

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
    'field',
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
