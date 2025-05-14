<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\Core\Form\FormState;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\ConfigValidationInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanHeadlineFigures;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;

/**
 * Tests the plan headline figures block plugin.
 *
 * @group ghi_blocks
 */
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
    $this->assertCount(7, $allowed_item_types);
    $this->assertArrayHasKey('item_group', $allowed_item_types);
    $this->assertArrayHasKey('line_break', $allowed_item_types);
    $this->assertArrayHasKey('funding_data', $allowed_item_types);
    $this->assertArrayHasKey('entity_counter', $allowed_item_types);
    $this->assertArrayHasKey('project_counter', $allowed_item_types);
    $this->assertArrayHasKey('attachment_data', $allowed_item_types);
    $this->assertArrayHasKey('label_value', $allowed_item_types);

    $this->assertNull($plugin->label());

    $definition = $plugin->getPluginDefinition();
    $this->assertIsArray($definition['config_forms']);
    $this->assertCount(2, $definition['config_forms']);
    $this->assertArrayHasKey($plugin->getDefaultSubform(), $definition['config_forms']);
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
    $configuration = $configuration !== FALSE ? [
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
    ] : [];
    $contexts = $this->getPlanSectionContexts();
    return $this->createBlockPlugin('plan_headline_figures', $configuration ?: [], $contexts);
  }

}
