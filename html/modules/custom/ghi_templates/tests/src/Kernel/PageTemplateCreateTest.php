<?php

namespace Drupal\Tests\ghi_templates\Kernel;

use Drupal\ghi_templates\Entity\PageTemplate;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;
use Drupal\Tests\ghi_sections\Traits\SectionTestTrait;

/**
 * Tests the creation and validation of page template entities.
 *
 * @group ghi_templates
 */
class PageTemplateCreateTest extends KernelTestBase {

  use FieldTestTrait;
  use EntityReferenceFieldCreationTrait;
  use SectionTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'taxonomy',
    'field',
    'text',
    'filter',
    'token',
    'path',
    'path_alias',
    'pathauto',
    'ghi_base_objects',
    'ghi_sections',
    'ghi_templates',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('base_object');
    $this->installEntitySchema('page_template');
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'field']);

    $this->createSectionType();
  }

  /**
   * Test that label is unique.
   */
  public function testUniqueLabel() {
    $section = $this->createSection();
    // Create a page template.
    $page_template = PageTemplate::create([
      'title' => '2023',
      'field_entity_reference' => $section->id(),
    ]);
    $page_template->save();

    // Create another page template with the same name which should fail.
    $page_template = PageTemplate::create([
      'title' => '2023',
      'field_entity_reference' => $section->id(),
    ]);
    $violations = $page_template->validate();
    $this->assertEquals(1, $violations->count());
  }

}
