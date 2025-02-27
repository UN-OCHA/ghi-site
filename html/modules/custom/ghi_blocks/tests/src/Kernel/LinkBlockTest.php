<?php

namespace Drupal\Tests\ghi_blocks\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OptionalTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Generic\Links;
use Drupal\ghi_image\CropManager;

/**
 * Tests the link block plugin.
 *
 * @group ghi_blocks
 */
class LinkBlockTest extends BlockKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock the crop manager.
    $crop_manager = $this->prophesize(CropManager::class);
    $this->container->set('ghi_image.crop_manager', $crop_manager->reveal());
    \Drupal::setContainer($this->container);
  }

  /**
   * Tests the block properties.
   */
  public function testBlockProperties() {
    $plugin = $this->getBlockPlugin();
    $this->assertInstanceOf(Links::class, $plugin);
    $this->assertInstanceOf(MultiStepFormBlockInterface::class, $plugin);
    $this->assertInstanceOf(OptionalTitleBlockInterface::class, $plugin);
    $this->assertInstanceOf(ConfigurableTableBlockInterface::class, $plugin);

    $allowed_item_types = $plugin->getAllowedItemTypes();
    $this->assertCount(1, $allowed_item_types);
    $this->assertArrayHasKey('link', $allowed_item_types);

    $definition = $plugin->getPluginDefinition();
    $this->assertArrayHasKey($plugin->getDefaultSubform(), $definition['config_forms']);
    $this->assertArrayHasKey($plugin->getTitleSubform(), $definition['config_forms']);
  }

  /**
   * Tests the block build.
   */
  public function testBlockBuild() {
    $plugin = $this->getBlockPlugin();
    $build = $plugin->buildContent();
    $this->assertEquals('item_list', $build['#theme']);
    $this->assertCount(1, $build['#items']);
  }

  /**
   * Tests the block with empty links.
   */
  public function testBlockBuildNoLinks() {
    $plugin = $this->getBlockPlugin(0);
    $this->assertNull($plugin->buildContent());
  }

  /**
   * Tests the block forms.
   */
  public function testBlockForms() {
    $plugin = $this->getBlockPlugin();

    $form_state = new FormState();
    $form_state->set('block', $plugin);
    $form = $plugin->linksForm([], $form_state);
    $this->assertEquals('configuration_container', $form['links']['#type']);

    $form = $plugin->displayForm([], $form_state);
    $this->assertEquals([], $form);
  }

  /**
   * Get a block plugin.
   *
   * @param int $link_count
   *   The number of link items to add.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Generic\Links
   *   The block plugin.
   */
  private function getBlockPlugin($link_count = 1) {
    $links = $link_count > 0 ? array_map(function ($id) {
      return $this->buildLinkItem($id);
    }, range(1, $link_count)) : [];
    $configuration = [
      'links' => [
        'links' => $links,
      ],
    ];
    return $this->createBlockPlugin('links', $configuration);
  }

  /**
   * Build a link item.
   *
   * @param int $id
   *   The id to set for the link.
   *
   * @return array
   *   A configuration item for a link item.
   */
  private function buildLinkItem($id) {
    return [
      'id' => $id,
      'item_type' => 'link',
      'config' => [
        'label' => 'Test link',
        'link' => [
          'link' => [
            'label' => NULL,
            'link_type' => 'custom',
            'link_custom' => [
              'url' => 'https://google.com',
            ],
            'link_related' => [
              'target' => NULL,
            ],
          ],
        ],
        'image' => [
          'image' => [],
        ],
        'content' => [
          'date' => '2024-05-22',
          'description' => [
            'value' => '',
            'format' => 'wysiwyg_simple',
          ],
          'description_toggle' => 0,
        ],
      ],
    ];
  }

}
