<?php

namespace Drupal\Tests\ghi_subpages_custom\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\ghi_sections\Entity\Section;
use Drupal\ghi_subpages_custom\Entity\CustomSubpage;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\ghi_sections\Kernel\SectionMenuTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the custom subpage manager.
 *
 * @group ghi_subpages_custom
 */
class CustomSubpageManagerTest extends SectionMenuTestBase {

  use UserCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ghi_subpages_custom',
  ];

  const CUSTOM_SUBPAGE_BUNDLE = 'custom_subpage';

  /**
   * The custom subpage manager to test.
   *
   * @var \Drupal\ghi_subpages_custom\CustomSubpageManager
   */
  protected $customSubpageManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    NodeType::create(['type' => self::CUSTOM_SUBPAGE_BUNDLE])->save();
    $this->createSectionReferenceField(self::CUSTOM_SUBPAGE_BUNDLE);

    $this->customSubpageManager = \Drupal::service('ghi_subpages_custom.manager');
  }

  /**
   * Tests that tags can be imported.
   */
  public function testLoadNodesForSection() {

    // Create a section.
    $section = $this->createSection();

    // Create custom subpages.
    $custom_subpage_1 = $this->createCustomSubpage($section);
    $custom_subpage_2 = $this->createCustomSubpage($section);

    $this->assertEquals([$custom_subpage_1->id(), $custom_subpage_2->id()], array_keys($this->customSubpageManager->loadNodesForSection($section)));

  }

  /**
   * Tests section menu items for custom subpages.
   */
  public function testCustomSubpageMenuItem() {

    // Create a section.
    $section = $this->createSection();
    $this->sectionMenuStorage->setSection($section);

    /** @var \Drupal\ghi_subpages_custom\Plugin\SectionMenuItem\CustomSubpage $menu_plugin */
    $menu_plugin = $this->sectionMenuPluginManager->createInstance('custom_subpage');
    $menu_plugin->setSection($section);
    $menu_item = $menu_plugin->getItem();
    $this->assertNull($menu_item);

    // Create custom subpages.
    $this->createCustomSubpage($section);
    $this->createCustomSubpage($section);

    $menu_item = $menu_plugin->getItem();
    $this->assertNull($menu_item);

    $form = [];
    $form_state = new FormState();
    $form = $menu_plugin->buildForm($form, $form_state);
    $this->assertCount(2, $form['node_id']['#options']);

  }

  /**
   * Create a section node.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNode
   *   A section node.
   */
  protected function createCustomSubpage(Section $section) {
    $custom_subpage = CustomSubpage::create([
      'type' => self::CUSTOM_SUBPAGE_BUNDLE,
      'title' => $this->randomString(),
      'uid' => 0,
      'field_entity_reference' => [
        'target_id' => $section->id(),
      ],
    ]);
    $custom_subpage->save();
    return $custom_subpage;
  }

}
