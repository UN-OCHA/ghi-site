<?php

namespace Drupal\Tests\ghi_sections\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\pathauto\PathautoState;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_sections\Traits\SectionTestTrait;
use Drupal\Tests\pathauto\Functional\PathautoTestHelperTrait;

/**
 * Test class for section aliases tests.
 *
 * @group ghi_sections
 */
class SectionAliasTest extends KernelTestBase {

  use EntityReferenceFieldCreationTrait;
  use SectionTestTrait;
  use PathautoTestHelperTrait;

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
    'layout_builder',
    'layout_discovery',
    'hpc_api',
    'hpc_common',
    'ghi_base_objects',
    'ghi_sections',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('base_object');
    $this->installEntitySchema('path_alias');
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'field', 'pathauto']);

    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $this->createSectionType();
  }

  /**
   * Test section aliases.
   */
  public function testSectionAlias() {
    $section = $this->createSection([
      'title' => 'Section 1 title',
    ]);
    $this->assertInstanceOf('\\Drupal\ghi_sections\\Entity\\Section', $section);

    $expected_section_alias = '/' . $section->getBaseObject()->bundle() . '/' . $section->getBaseObject()->getSourceId();
    $this->assertEntityAlias($section, $expected_section_alias);

    // Change the alias of the section.
    $section->path->alias = '/content/custom-section-title';
    $section->path->pathauto = PathautoState::SKIP;
    $section->save();

    $section = Node::load($section->id());
    $this->assertEntityAlias($section, '/content/custom-section-title');
  }

}
