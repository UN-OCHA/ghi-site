<?php

namespace Drupal\Tests\ghi_blocks\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\SectionComponent;
use Drupal\Tests\ghi_blocks\Traits\PrivateMethodTrait;

/**
 * Base class for block kernel tests.
 *
 * @group ghi_blocks
 */
abstract class BlockKernelTestBase extends KernelTestBase {

  use PrivateMethodTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'layout_builder',
    'layout_discovery',
    'migrate',
    'hpc_api',
    'ghi_form_elements',
    'ghi_sections',
    'ghi_blocks',
    'ghi_base_objects',
  ];

  /**
   * Get a section component.
   *
   * @param string $plugin_id
   *   The plugin id.
   * @param array $configuration
   *   The hpc-specific configuration.
   * @param string $label
   *   The label.
   * @param bool $label_display
   *   Whether the label should be displayed or not.
   *
   * @return \Drupal\layout_builder\SectionComponent
   *   The block plugin.
   */
  protected function createSectionComponent($plugin_id, $configuration, $label = '<none>', $label_display = FALSE) {
    $configuration = [
      'id' => $plugin_id,
      'label' => $label,
      'label_display' => $label_display,
      'provider' => 'ghi_blocks',
      'hpc' => $configuration,
    ];
    return new SectionComponent('10000000-0000-1000-a000-000000000000', 'content', $configuration);
  }

  /**
   * Get a block plugin.
   *
   * @param string $plugin_id
   *   The plugin id.
   * @param array $configuration
   *   The hpc-specific configuration.
   * @param array $contexts
   *   An array of context objects.
   * @param string $label
   *   The label.
   * @param bool $label_display
   *   Whether the label should be displayed or not.
   *
   * @return \Drupal\hpc_common\Plugin\HPCPluginInterface
   *   The block plugin.
   */
  protected function createBlockPlugin($plugin_id, $configuration, array $contexts = [], $label = '<none>', $label_display = FALSE) {
    $plugin = $this->createSectionComponent($plugin_id, $configuration, $label, $label_display)?->getPlugin($contexts);
    return $plugin;
  }

}
