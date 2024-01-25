<?php

namespace Drupal\Tests\ghi_sections\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\pathauto\PathautoGeneratorInterface;
use Drupal\pathauto\PathautoState;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_sections\Traits\SectionTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\pathauto\Functional\PathautoTestHelperTrait;

/**
 * Test class for section aliases tests.
 *
 * @group ghi_sections
 */
class SectionAliasTest extends KernelTestBase {

  use ContentTypeCreationTrait;
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
    $section_pattern = $this->createPattern('node', '/content/[node:title]');
    $this->addBundleCondition($section_pattern, 'node', self::SECTION_BUNDLE);
    $section_pattern->save();

    $config = $this->config('pathauto.settings');
    $config->set('update_action', PathautoGeneratorInterface::UPDATE_ACTION_NO_NEW);
    $config->save();
  }

  /**
   * Test section aliases.
   */
  public function testSectionAlias() {
    $section = $this->createSection([
      'title' => 'Section 1 title',
      'path' => [
        'pathauto' => PathautoState::CREATE,
      ],
    ]);
    $this->assertInstanceOf('\\Drupal\ghi_sections\\Entity\\Section', $section);
    $this->assertEntityAlias($section, '/content/section-1-title');
  }

}
