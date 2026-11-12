<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Core\Config\FileStorage;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests node layouts cannot add the page-manager-only homepage stage.
 */
#[Group('ghi_blocks')]
class LinkCarouselPlacementConfigTest extends UnitTestCase {

  /**
   * Tests every exported Layout Builder node display excludes the stage.
   */
  public function testNodeLayoutsExcludeHomepageStage(): void {
    $storage = new FileStorage(dirname(__DIR__, 7) . '/config');
    $checked = 0;
    foreach ($storage->listAll('core.entity_view_display.node.') as $name) {
      $settings = $storage->read($name)['third_party_settings'] ?? [];
      if (empty($settings['layout_builder']['enabled'])) {
        continue;
      }
      $denied = $settings['layout_builder_restrictions']['entity_view_mode_restriction']['denylisted_blocks']['Generic elements'] ?? [];
      $this->assertContains('generic_link_carousel', $denied, $name);
      $checked++;
    }
    $this->assertGreaterThan(0, $checked);
  }

}
