<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Global;

use Drupal\Core\Form\FormState;
use Drupal\file\Entity\File;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Generic\LinkCarousel;
use Drupal\ghi_blocks\Plugin\Block\ImageProviderBlockInterface;
use Drupal\Tests\ghi_blocks\Kernel\BlockKernelTestBase;

/**
 * Tests the link carousel block plugin.
 *
 * @group ghi_blocks
 */
class LinkCarouselBlockTest extends BlockKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'user',
    'file',
    'responsive_image',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
  }

  /**
   * Tests the block properties.
   */
  public function testBlockProperties() {
    $plugin = $this->getBlockPlugin();
    $this->assertInstanceOf(LinkCarousel::class, $plugin);
    $this->assertInstanceOf(ConfigurableTableBlockInterface::class, $plugin);
    $this->assertInstanceOf(ImageProviderBlockInterface::class, $plugin);

    $allowed_item_types = $plugin->getAllowedItemTypes();
    $this->assertCount(1, $allowed_item_types);
    $this->assertArrayHasKey('carousel_item', $allowed_item_types);
  }

  /**
   * Tests the block build.
   */
  public function testBlockBuild() {
    $plugin = $this->getBlockPlugin(5);
    $build = $plugin->buildContent();
    $this->assertEquals('link_carousel', $build['#theme']);
    $this->assertCount(5, $build['#items']);
    $this->assertNotNull($plugin->provideImageUri());

    $files = $this->callPrivateMethod($plugin, 'getFiles');
    $this->assertIsArray($files);
    $this->assertCount(5, $files);
  }

  /**
   * Tests the block with empty links.
   */
  public function testBlockBuildNoLinks() {
    $plugin = $this->getBlockPlugin(0);
    $this->assertNull($plugin->buildContent());
    $this->assertNull($plugin->provideImageUri());

    $files = $this->callPrivateMethod($plugin, 'getFiles');
    $this->assertIsArray($files);
    $this->assertCount(0, $files);
  }

  /**
   * Tests the block forms.
   */
  public function testBlockForms() {
    $plugin = $this->getBlockPlugin();

    $form_state = new FormState();
    $form_state->set('block', $plugin);
    $form = $plugin->getConfigForm([], $form_state);
    $this->assertEquals('configuration_container', $form['items']['#type']);
  }

  /**
   * Get a block plugin.
   *
   * @param int $link_count
   *   The number of carousel items to add.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Generic\LinkCarousel
   *   The block plugin.
   */
  private function getBlockPlugin($link_count = 1) {
    $links = $link_count > 0 ? array_map(function ($id) {
      return $this->buildItem($id);
    }, range(1, $link_count)) : [];
    return $this->createBlockPlugin('generic_link_carousel', ['items' => $links]);
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
  private function buildItem($id) {
    return [
      'id' => $id,
      'id' => $id,
      'pid' => NULL,
      'item_type' => 'carousel_item',
      'config' => [
        'label' => $this->randomString(),
        'value' => [
          'tag_line' => $this->randomString(),
          'description' => $this->randomString(),
          'image' => [$this->createFile()->id()],
          'image_caption' => '',
          'url' => 'https://crisisrelief.un.org/donate',
          'button_label' => 'Donate here',
        ],
      ],
    ];
  }

  /**
   * Create a file object.
   *
   * @return \Drupal\file\Entity\File
   *   The created file.
   */
  private function createFile() {
    // Create a test file object.
    $filename = $this->randomString() . '.png';
    $file = File::create([
      'filename' => $filename,
      'filesize' => 100,
      'uri' => 'public://images/' . $filename,
      'filemime' => 'image/png',
    ]);
    $file->save();
    $this->assertFalse($file->isPermanent());
    return $file;
  }

}
