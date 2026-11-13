<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Global;

use Drupal\ghi_blocks\Plugin\Block\GlobalPage\Homepages;
use Drupal\ghi_homepage\Entity\Homepage;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the year-switchable homepages block plugin.
 */
#[Group('ghi_blocks')]
class HomepagesTest extends PlanBlockKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ghi_homepage'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    NodeType::create(['type' => 'homepage', 'name' => 'Homepage'])->save();
    $this->createField('node', 'homepage', 'integer', 'field_year', 'Year');

    Homepage::create([
      'type' => 'homepage',
      'title' => 'Global Humanitarian Overview 2027',
      'field_year' => 2027,
      'status' => TRUE,
    ])->save();
  }

  /**
   * Tests the default and configurable internally rendered titles.
   */
  public function testTitle(): void {
    $block = $this->createHomepageBlock();
    $this->assertTrue($block->shouldDisplayTitle());
    $this->assertSame('Operations', $block->getDefaultTitle());
    $this->assertSame('Operations', $block->label());
    $this->assertSame('<h2 class="cd-block-title">Operations</h2>', (string) $block->buildContent()['title_wrapper']['title']['#markup']);

    $block = $this->createHomepageBlock('Current operations');
    $this->assertSame('Current operations', $block->label());
    $this->assertSame('<h2 class="cd-block-title">Current operations</h2>', (string) $block->buildContent()['title_wrapper']['title']['#markup']);
  }

  /**
   * Creates the block with an optional configured title.
   */
  private function createHomepageBlock(string $label = '<none>'): Homepages {
    return $this->createBlockPlugin('global_homepages', ['year' => 2027], label: $label);
  }

}
