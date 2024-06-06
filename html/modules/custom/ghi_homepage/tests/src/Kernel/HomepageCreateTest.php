<?php

namespace Drupal\Tests\ghi_homepage\Kernel;

use Drupal\ghi_homepage\Entity\Homepage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;

/**
 * Tests the creation and validation of homepage nodes.
 *
 * @group ghi_homepage
 */
class HomepageCreateTest extends KernelTestBase {

  use FieldTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'token',
    'path_alias',
    'pathauto',
    'ghi_homepage',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'field', 'pathauto']);

    NodeType::create(['type' => Homepage::BUNDLE])->save();
    $this->createField('node', Homepage::BUNDLE, 'integer', 'field_year', 'Year');
  }

  /**
   * Test that year is unique.
   */
  public function testUniqueYear() {
    // Create a homepage for 2023.
    $homepage = Homepage::create([
      'type' => Homepage::BUNDLE,
      'title' => '2023',
      'field_year' => 2023,
    ]);
    $homepage->save();

    // Try to create another homepage for 2023 which should fail.
    $homepage = Homepage::create([
      'type' => Homepage::BUNDLE,
      'title' => '2023',
      'field_year' => 2023,
    ]);
    $violations = $homepage->validate();
    $this->assertNotEmpty($violations);
  }

}
