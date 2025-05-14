<?php

namespace Drupal\Tests\ghi_subpages\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\ghi_subpages\Traits\SubpageTestTrait;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Drupal\hpc_common\Helpers\StringHelper;

/**
 * Test class for section subpages tests.
 *
 * @group ghi_subpages
 */
class SubpagesTest extends KernelTestBase {

  use SubpageTestTrait;

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
    'migrate',
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
  }

  /**
   * Test the usage of the bundle classes.
   */
  public function testBundleClasses() {
    foreach (self::SUBPAGE_BUNDLES as $bundle_name) {
      $subpage = $this->entityTypeManager->getStorage('node')->create([
        'type' => $bundle_name,
        'title' => $this->randomMachineName(),
      ]);
      $subpage->save();
      $this->assertInstanceOf('\\Drupal\ghi_subpages\\Entity\\' . StringHelper::makeCamelCase($bundle_name, FALSE) . 'Subpage', $subpage);
    }
  }

  /**
   * Test that subpages are created and deleted together with a section.
   */
  public function testSectionSubpageLogic() {
    $existing_subpages = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => self::SUBPAGE_BUNDLES,
    ]);
    $this->assertEmpty($existing_subpages);

    // Create a section, which should also create the subpages.
    $section = $this->createSection();

    // Confirm that we have the expected number of subpages now.
    $existing_subpages = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => self::SUBPAGE_BUNDLES,
    ]);
    // Confirm every subpage has the reference to the section set.
    $this->assertCount(count(self::SUBPAGE_BUNDLES), $existing_subpages);
    foreach ($existing_subpages as $subpage) {
      $this->assertInstanceOf(SubpageNodeInterface::class, $subpage);
      $parent_node = $subpage->getParentBaseNode();
      $this->assertInstanceOf(SectionNodeInterface::class, $parent_node);
      $this->assertEquals($section->id(), $parent_node->id());
    }

    // Delete the section, which should also delete the subpages.
    $section->delete();
    $existing_subpages = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => self::SUBPAGE_BUNDLES,
    ]);
    $this->assertEmpty($existing_subpages);
  }

}
