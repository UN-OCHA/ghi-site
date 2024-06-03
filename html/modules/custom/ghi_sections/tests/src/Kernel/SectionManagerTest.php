<?php

namespace Drupal\Tests\ghi_sections\Kernel;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\ghi_sections\Entity\Section;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_sections\Traits\SectionTestTrait;
use Drupal\Tests\pathauto\Functional\PathautoTestHelperTrait;

/**
 * Test class for section manager tests.
 *
 * @group ghi_sections
 */
class SectionManagerTest extends KernelTestBase {

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
    'ghi_plans',
    'ghi_sections',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The section manager.
   *
   * @var \Drupal\ghi_sections\SectionManager
   */
  protected $sectionManager;

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
    $this->sectionManager = $this->container->get('ghi_sections.manager');

    $this->createSectionType();
  }

  /**
   * Test the getCurrentSection method.
   */
  public function testGetCurrentSection() {
    $section = $this->prophesize(Section::class);
    $this->assertEquals($section->reveal(), $this->sectionManager->getCurrentSection($section->reveal()));

    $node = $this->prophesize(NodeInterface::class);
    $module_handler = $this->prophesize(ModuleHandlerInterface::class);
    $module_handler->alter('current_section', NULL, $node->reveal())->shouldBeCalled();
    $this->sectionManager->setModuleHandler($module_handler->reveal());
    $this->assertNull($this->sectionManager->getCurrentSection($node->reveal()));
  }

  /**
   * Test the createSectionForBaseObject method.
   */
  public function testCreateSectionForBaseObject() {
    $base_object = $this->createBaseObject([
      'type' => 'plan',
      'field_year' => 2024,
    ]);
    $team = $this->createTeam();
    $tag = $this->createTag();

    // Creating a section without a team should fail.
    $result = $this->sectionManager->createSectionForBaseObject($base_object, [
      'tags' => ['target_id' => $tag->id()],
    ]);
    $this->assertFalse($result);

    // Creating a section without tags should work, as long as there is a team.
    $section = $this->sectionManager->createSectionForBaseObject($base_object, [
      'team' => $team,
    ]);
    $this->assertInstanceOf(SectionNodeInterface::class, $section);

    // And setting both should obviously work too, but only afte we delete the
    // previously created section. This also tests that we can't create
    // multiple sections for the same base object.
    $result = $this->sectionManager->createSectionForBaseObject($base_object, [
      'team' => $team,
      'tags' => ['target_id' => $tag->id()],
    ]);
    $this->assertFalse($result);

    // Delete the section and try again.
    $section->delete();
    $section = $this->sectionManager->createSectionForBaseObject($base_object, [
      'team' => $team,
      'tags' => ['target_id' => $tag->id()],
    ]);
    $this->assertInstanceOf(SectionNodeInterface::class, $section);

    // We should be able to load the section by its base object now.
    $section = $this->sectionManager->loadSectionForBaseObject($base_object);
    $this->assertInstanceOf(Section::class, $section);
  }

  /**
   * Test the loadSectionsForTeam method.
   */
  public function testLoadSectionsForTeam() {
    $team = $this->createTeam();
    $section_with_team = $this->createSection([
      'field_team' => [
        'target_id' => $team->id(),
      ],
    ]);
    $this->createSection();
    $result = $this->sectionManager->loadSectionsForTeam($team);
    $this->assertEquals([$section_with_team->id()], array_keys($result));
  }

}
