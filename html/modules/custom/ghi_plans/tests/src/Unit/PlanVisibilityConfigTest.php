<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\Core\Config\FileStorage;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the locally managed global visibility setting for plans.
 */
#[Group('ghi_plans')]
class PlanVisibilityConfigTest extends UnitTestCase {

  /**
   * Tests new plans are hidden independently of their released state.
   */
  public function testNewPlansDefaultToHidden(): void {
    $storage = new FileStorage(dirname(__DIR__, 7) . '/config');
    $field = $storage->read('field.field.base_object.plan.field_visible_on_global_pages');
    $migration = $storage->read('migrate_plus.migration.plan');

    $this->assertSame([['value' => 0]], $field['default_value']);
    $this->assertSame('released', $migration['process']['field_released']);
    $this->assertArrayNotHasKey('field_visible_on_global_pages', $migration['process']);
    $this->assertNotContains('field_visible_on_global_pages', $migration['destination']['overwrite_properties']);
  }

}
