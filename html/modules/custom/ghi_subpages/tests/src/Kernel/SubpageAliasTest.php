<?php

namespace Drupal\Tests\ghi_subpages\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\pathauto\PathautoGeneratorInterface;
use Drupal\pathauto\PathautoState;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_subpages\Traits\SubpageTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\pathauto\Functional\PathautoTestHelperTrait;

/**
 * Test class for section aliases tests.
 *
 * @group ghi_subpages
 */
class SubpageAliasTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use EntityReferenceFieldCreationTrait;
  use SubpageTestTrait;
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
    'ghi_subpages',
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

    $this->createSubpageContentTypes();

    $section_pattern = $this->createPattern('node', '/content/[node:title]');
    $this->addBundleCondition($section_pattern, 'node', self::SECTION_BUNDLE);
    $section_pattern->save();

    foreach (self::SUBPAGE_BUNDLES as $bundle) {
      $pattern = $this->createPattern('node', '/[node:field_entity_reference:entity:url:path]/' . $bundle);
      $this->addBundleCondition($pattern, 'node', $bundle);
      $pattern->save();
    }

    $config = $this->config('pathauto.settings');
    $config->set('update_action', PathautoGeneratorInterface::UPDATE_ACTION_LEAVE);
    $config->save();
  }

  /**
   * Test section aliases.
   */
  public function testSubpageAlias() {
    $section = $this->createSection([
      'title' => 'Section 1 title',
      'path' => [
        'pathauto' => PathautoState::CREATE,
      ],
    ]);
    $this->assertInstanceOf('\\Drupal\ghi_sections\\Entity\\Section', $section);
    $this->assertEntityAlias($section, '/content/section-1-title');

    $section = Node::load($section->id());

    // Confirm the subpage aliases.
    $existing_subpages = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => self::SUBPAGE_BUNDLES,
    ]);
    foreach ($existing_subpages as $subpage) {
      $this->assertEntityAlias($subpage, $section->path->alias . '/' . $subpage->bundle());
    }

    // Change the alias of the section.
    $section->path->alias = '/content/custom-section-title';
    $section->path->pathauto = PathautoState::SKIP;
    $section->save();

    $section = Node::load($section->id());
    $this->assertEntityAlias($section, '/content/custom-section-title');

    // Confirm the subpage aliases have updated too.
    $existing_subpages = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => self::SUBPAGE_BUNDLES,
    ]);
    foreach ($existing_subpages as $subpage) {
      $this->assertEntityAlias($subpage, '/content/custom-section-title/' . $subpage->bundle());
    }

  }

}
