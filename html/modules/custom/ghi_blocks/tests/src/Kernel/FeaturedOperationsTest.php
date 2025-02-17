<?php

namespace Drupal\Tests\ghi_blocks\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\ghi_blocks\Plugin\Block\GlobalPage\FeaturedOperations;

/**
 * Tests the featured operations block plugin.
 *
 * @group ghi_blocks
 */
class FeaturedOperationsTest extends BlockKernelTestBase {

  /**
   * Tests the block properties.
   */
  public function testBlockProperties() {
    $plugin = $this->createBlockPlugin('global_featured_operations', []);
    $this->assertInstanceOf(FeaturedOperations::class, $plugin);

    $this->assertEmpty($plugin->getConfigForm([], new FormState()));

    $year = 2024;
    $plugin->setContextValue('year', $year);
    $build = $plugin->buildContent();
    $this->assertEquals([
      '#type' => 'view',
      '#name' => 'featured_sections',
      '#display_id' => 'block_sections_featured_3',
      '#arguments' => [
        $year,
      ],
    ], $build);
  }

}
